<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/query-builder-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=query-builder-for-laravel">
    <img src="art/hero.png" alt="Query Builder for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/query-builder-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/query-builder-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/query-builder-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/query-builder-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/query-builder-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/query-builder-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=query-builder-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Query Builder for Laravel

Allow-list-driven filtering, sorting and pagination for Laravel API list endpoints. It reads the
query string (`filter[...]`, `sort=`, `page`, `per_page`) and applies only the filters and sorts a
controller explicitly permits — anything else is rejected with a 400.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/query-builder-for-laravel
```

## Usage

Declare what a list endpoint may filter and sort, then use it like any Eloquent builder:

```php
use RoundlyConsulting\QueryBuilder\AllowedFilter;
use RoundlyConsulting\QueryBuilder\AllowedSort;
use RoundlyConsulting\QueryBuilder\QueryBuilder;

// GET /posts?filter[status]=published,draft&filter[title]=laravel&sort=-popularity&per_page=50
public function index(ListPostsRequest $request)
{
    return QueryBuilder::for(Post::class)
        ->allowedFilters(
            AllowedFilter::exact('status'),                   // a comma list becomes whereIn
            AllowedFilter::partial('title'),                  // escaped LIKE, ILIKE on Postgres
            AllowedFilter::scope('published', booleans: true),
            AllowedFilter::trashed(),
        )
        ->allowedSorts('title', AllowedSort::field('popularity', 'views'))
        ->defaultSort('-created_at')
        ->paginate($request->perPage());
}
```

`HasPageSize` validates `per_page` on the form request — above the configured maximum is a 422:

```php
use Illuminate\Foundation\Http\FormRequest;
use RoundlyConsulting\QueryBuilder\Concerns\HasPageSize;

final class ListPostsRequest extends FormRequest
{
    use HasPageSize;

    public function rules(): array
    {
        return $this->pageSizeRules();
    }
}
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/query-builder-for-laravel](https://roundly-consulting.com/open-source/docs/query-builder-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=query-builder-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=query-builder-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=query-builder-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md). Maintained by roundly-consulting.
