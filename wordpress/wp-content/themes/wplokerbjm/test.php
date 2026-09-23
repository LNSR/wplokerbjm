<?php

declare(strict_types=1);

const ITERATIONS = 1_000_000;

$key = 'foo, bar, baz, foo, bar, qux, baz, foo';

function cachedPipeline(string $key): string
{
    static $v1;
    static $v2;
    static $v3;
    static $v4;
    static $v5;

    return $key
        |> ($v1 ??= static fn($v) => explode(', ', $v))
        |> ($v2 ??= static fn($v) => array_map('trim', $v))
        |> ($v3 ??= array_filter(...))
        |> ($v4 ??= array_unique(...))
        |> ($v5 ??= static fn($v) => implode(', ', $v));
}

function freshPipeline(string $key): string
{
    return $key
        |> (static fn($v) => explode(', ', $v))
        |> (static fn($v) => array_map('trim', $v))
        |> (array_filter(...))
        |> (array_unique(...))
        |> (static fn($v) => implode(', ', $v));
}

function conventional(string $key): string
{
    return implode(
        ', ',
        array_unique(
            array_filter(
                array_map(
                    'trim',
                    explode(', ', $key)
                )
            )
        )
    );
}

function benchmark(
    string $name,
    callable $fn,
    string $input,
    int $iterations,
): array {
    // Warmup
    for ($i = 0; $i < 10_000; ++$i) {
        $fn($input);
    }

    gc_collect_cycles();

    $start = hrtime(true);

    $result = '';
    for ($i = 0; $i < $iterations; ++$i) {
        $result = $fn($input);
    }

    $elapsedNs = hrtime(true) - $start;

    return [
        'name' => $name,
        'time_ms' => $elapsedNs / 1_000_000,
        'ns/op' => $elapsedNs / $iterations,
        'result' => $result,
    ];
}

$results = [
    benchmark('Cached pipe', 'cachedPipeline', $key, ITERATIONS),
    benchmark('Fresh pipe', 'freshPipeline', $key, ITERATIONS),
    benchmark('Conventional', 'conventional', $key, ITERATIONS),
];

echo PHP_EOL;
echo sprintf("%-20s %12s %12s %s\n", 'Implementation', 'Time (ms)', 'ns/op', 'Result');
echo str_repeat('-', 70) . PHP_EOL;

foreach ($results as $result) {
    echo sprintf(
        "%-20s %12.3f %12.1f %s\n",
        $result['name'],
        $result['time_ms'],
        $result['ns/op'],
        $result['result'],
    );
}

echo PHP_EOL;