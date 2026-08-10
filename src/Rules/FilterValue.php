<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\QueryBuilder\DataTransferObjects\RequestedFilterValues;
use RoundlyConsulting\QueryBuilder\Enums\RequestedOperator;
use RoundlyConsulting\QueryBuilder\Support\FilterSentinel;

/**
 * Validate a filter parameter's VALUE, ignoring the operator prefix and sentinels the
 * filter itself understands.
 *
 * A list endpoint usually validates what a filter may contain — `Rule::in($levels)`, an
 * `integer`, a `uuid` — and that validation is what makes a typo a helpful 422 instead of
 * a silently empty list. The moment the same filter accepts an operator, every one of
 * those rules starts rejecting the wire: `gte:500` is not an integer and `not:debug` is
 * not one of the levels, so the request 422s on a value the filter would have handled
 * perfectly.
 *
 * Wrapping the rules fixes it in the one place that knows about the wire:
 *
 * ```php
 * 'filter.level' => ['nullable', new FilterValue(Rule::enum(LogLevel::class))],
 * 'filter.code' => ['nullable', new FilterValue('integer', operators: self::NUMERIC)],
 * 'filter.project' => ['nullable', new FilterValue('uuid', sentinels: [FilterSentinel::NONE])],
 * ```
 *
 * The prefix is stripped and the ORIGINAL rules run against what remains, so the endpoint
 * keeps exactly the validation it declared — including its message and attribute name.
 *
 * **Pass the same operators and sentinel the FILTER declared.** The two halves are one
 * declaration, and they drift in opposite, equally quiet ways: a rule that strips more than
 * the filter honours accepts `gte:debug` here and then matches nothing there (an empty list
 * that looks like it worked), and a rule that knows nothing of a `none` sentinel 422s the
 * one value the filter exists to answer.
 *
 * Each element of a comma/array value is validated, because a multi-select sends several
 * and one bad one is still a bad request; the operator can only ride on the first, which is
 * where the filters read it from.
 */
final readonly class FilterValue implements ValidationRule
{
    /** @var list<RequestedOperator> */
    private array $operators;

    /**
     * @param  array<int, mixed>|string|object  $rules  the rules that would have applied
     *                                                  to a bare value — a rule string, a
     *                                                  list of them, or a rule object
     * @param  ?list<RequestedOperator>  $operators  the tokens the FILTER declared;
     *                                               defaults to the equality pair, so a
     *                                               comparison or partial filter has to say
     *                                               so rather than inherit it
     * @param  list<string>  $sentinels  reserved values the filter answers itself
     *                                   ({@see FilterSentinel::NONE}), which its column
     *                                   rules would reject
     * @param  bool  $partialByDefault  mirror the filter's own flag: it decides which
     *                                  operator a BARE value means, and that one is always
     *                                  nameable
     */
    public function __construct(
        private array|string|object $rules,
        ?array $operators = null,
        private array $sentinels = [],
        bool $partialByDefault = false,
    ) {
        // The filter's own default is always nameable — `RequestedOperatorFilter` prepends
        // it unconditionally, so a rule that does not strip it 422s the one request a
        // client makes to switch a chip back to plain equality.
        $default = $partialByDefault ? RequestedOperator::Contains : RequestedOperator::Is;

        $this->operators = array_values(array_unique(
            [$default, ...($operators ?? [RequestedOperator::Not])],
            SORT_REGULAR,
        ));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Split on commas first, mirroring `QueryBuilderRequest`'s own normalisation:
        // validation sees the RAW query-string value, so a multi-select still arrives as
        // one `a,b` string here and would fail an `in:` rule as a whole. The same cap the
        // request normaliser applies is applied here, or a 5 000-item value spins up 5 000
        // validators before the cap it is bounded by ever runs.
        // Split EVERY element, not just a bare string: `filter[level][]=debug,info` is a
        // legal wire value that the request normaliser flattens to two, so validating the
        // element whole would 422 a request the filter handles.
        $exploded = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            is_string($item)
                ? array_push($exploded, ...explode(',', $item))
                : $exploded[] = $item;
        }

        $parsed = RequestedFilterValues::parse(count($exploded) === 1 ? $exploded[0] : $exploded, $this->operators);

        $limit = max(1, (int) config('query-builder.limits.max_filter_values', 50));

        foreach (array_slice($parsed->values, 0, $limit) as $item) {
            if (in_array($item, $this->sentinels, true)) {
                continue;
            }

            $validator = Validator::make(
                Arr::undot([$attribute => $item]),
                [$attribute => $this->rules],
            );

            if ($validator->fails()) {
                $fail((string) $validator->errors()->first($attribute));

                return;
            }
        }
    }
}
