<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder;

use Illuminate\Support\ServiceProvider;

final class QueryBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-builder.php', 'query-builder');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'query-builder');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/query-builder.php' => config_path('query-builder.php'),
            ], 'query-builder-config');
        }
    }
}
