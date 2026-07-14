<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * The config-key contract, pinned in BOTH directions:
 *
 *   forward — every key the source reads must exist in the shipped config file
 *             (a key the code reads but the package never ships is unreachable);
 *   reverse — every key the config file ships must be read by the source
 *             (a key the package ships but nothing reads is a documented
 *             feature that silently does nothing).
 *
 * Keys are scraped from real string TOKENS, never the file text — a docblock
 * mentioning a key is not a read.
 */
function shippedConfig(): array
{
    return require dirname(__DIR__, 2).'/config/query-builder.php';
}

/**
 * @return list<string>
 */
function sourceFiles(): array
{
    $files = [];

    /** @var Iterator<string, SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Every `query-builder.*` key named by a string literal in `src/`.
 *
 * `$behaviourOnly` drops the service provider, whose `about` payload *renders*
 * every key. Displaying a value is not applying it — without this the reverse
 * direction below would pass for a key nothing but `php artisan about` reads.
 *
 * @return list<string>
 */
function configKeysReadBySource(bool $behaviourOnly = false): array
{
    $keys = [];

    foreach (sourceFiles() as $file) {
        if ($behaviourOnly && str_ends_with($file, 'QueryBuilderServiceProvider.php')) {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $literal = trim($token[1], "'\"");

            if (preg_match('/^query-builder\.[a-z0-9_.]+$/', $literal) === 1) {
                $keys[$literal] = true;
            }
        }
    }

    $keys = array_keys($keys);
    sort($keys);

    return $keys;
}

it('scrapes the config keys the source actually reads', function (): void {
    // Guard the guard: an empty (or text-matched) scrape would make both
    // directions below pass vacuously.
    expect(configKeysReadBySource())
        ->not->toBeEmpty()
        ->toContain('query-builder.parameters.filter')
        ->toContain('query-builder.mode.unknown_filter');
});

it('ships every config key the source reads', function (): void {
    $config = shippedConfig();

    foreach (configKeysReadBySource() as $key) {
        $relative = substr($key, strlen('query-builder.'));

        expect(Arr::has($config, $relative))->toBeTrue(
            "The source reads [{$key}], which config/query-builder.php does not ship.",
        );
    }
});

it('reads every config key it ships', function (): void {
    $read = configKeysReadBySource(behaviourOnly: true);

    foreach (array_keys(Arr::dot(shippedConfig())) as $leaf) {
        expect(in_array("query-builder.{$leaf}", $read, true))->toBeTrue(
            "config/query-builder.php ships [{$leaf}], which no line of src/ ever reads.",
        );
    }
});

it('never hides a config key behind string interpolation', function (): void {
    // An interpolated key ("query-builder.mode.{$name}") is invisible to the
    // scrape above, which would blind both directions of this contract.
    foreach (sourceFiles() as $file) {
        $source = (string) file_get_contents($file);

        expect($source)->not->toMatch('/"query-builder\.[^"]*\{\$/');
    }
});
