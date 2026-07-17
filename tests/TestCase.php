<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\QueryBuilder\QueryBuilderServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider query-builder needs, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction. The enums and
     * package-toolkit packages it hard-requires ship no provider of their own — the toolkit
     * ships the *base* this one extends — so query-builder's is the one.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [QueryBuilderServiceProvider::class];
    }

    /**
     * Deliberately empty: query-builder **ships no migrations**. It is a read-side query
     * API over tables a host already owns, so there is nothing to publish, nothing to
     * auto-load, and no migration order to pin — which is why this row adopts neither P nor
     * R nor M.
     *
     * The fixture tables below are host-owned stand-ins created ad hoc, not shipped schema.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [];
    }

    /**
     * The host-owned tables the filters and sorts run against. `parent::` is called first so
     * the base case keeps its contract even though it has no sources to load — an override
     * that silently replaced it would be the step-3a decapitation one level down.
     *
     * These are `Schema::create()` rather than migrations on purpose: nothing here is
     * shipped schema. They are portable across every driver this package's legs run
     * (sqlite/pgsql/mysql), which is what lets the same suite prove the filters on all three.
     *
     * The `dropIfExists` calls are a **local workaround for a testing-package gap**, not
     * belt-and-braces — remove them once it is fixed upstream and this suite stays green.
     * `PackageTestCase::tearDownInteractsWithMigrations()` skips its drop-all-tables reset
     * when `cachedTestMigratorProcessors === []`, on the assumption that a suite which
     * loaded no migrations has no tables to reset. That assumption does not hold here: this
     * package ships no migrations (so the cache is empty and the reset is skipped) yet
     * creates fixture tables right here. On SQLite `:memory:` it never mattered — the
     * database dies with the connection — but on a real engine the tables survive and the
     * *next* test dies on `relation "posts" already exists`, which is the exact failure mode
     * the base case's reset exists to prevent, reached by the one door it does not cover.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::dropIfExists('posts');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('archived_posts');

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedInteger('views')->default(0);
            $table->boolean('active')->default(false);
            $table->unsignedBigInteger('author_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('archived_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes('archived_at');
        });
    }
}
