<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Requests;

use Illuminate\Foundation\Http\FormRequest;
use RoundlyConsulting\QueryBuilder\Concerns\HasPageSize;

final class ListRequest extends FormRequest
{
    use HasPageSize;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->pageSizeRules();
    }
}
