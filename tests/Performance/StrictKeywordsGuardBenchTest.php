<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function microtime;
use function sprintf;

use const PHP_FLOAT_MAX;

#[CoversNothing]
final class StrictKeywordsGuardBenchTest extends TestCase
{
    private const int SCHEMA_COUNT = 400;

    private const int MEASUREMENT_ITERATIONS = 5;

    private const float GUARD_BUDGET_MILLISECONDS = 40.0;

    #[Test]
    public function guard_overhead_on_large_spec_stays_within_budget(): void
    {
        $spec = $this->largeSpec();

        $this->buildWithDuration($spec, strictKeywords: true);
        $this->buildWithDuration($spec, strictKeywords: false);

        $strictDuration = $this->bestBuildDuration($spec, strictKeywords: true);
        $annotationDuration = $this->bestBuildDuration($spec, strictKeywords: false);

        $overhead = $strictDuration - $annotationDuration;

        $this->assertLessThan(
            self::GUARD_BUDGET_MILLISECONDS,
            $overhead,
            sprintf(
                'Strict keywords guard overhead (%.2f ms; strict %.2f ms vs annotation %.2f ms) must stay within budget on a %d-schema spec',
                $overhead,
                $strictDuration,
                $annotationDuration,
                self::SCHEMA_COUNT,
            ),
        );
    }

    private function bestBuildDuration(string $spec, bool $strictKeywords): float
    {
        $best = PHP_FLOAT_MAX;

        for ($iteration = 0; $iteration < self::MEASUREMENT_ITERATIONS; ++$iteration) {
            $duration = $this->buildWithDuration($spec, $strictKeywords);

            if ($duration < $best) {
                $best = $duration;
            }
        }

        return $best;
    }

    private function buildWithDuration(string $spec, bool $strictKeywords): float
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString($spec);

        if (false === $strictKeywords) {
            $builder = $builder->disableStrictKeywords();
        }

        $start = microtime(true);
        $builder->build();

        return (microtime(true) - $start) * 1000.0;
    }

    private function largeSpec(): string
    {
        $schemas = [];

        for ($index = 0; $index < self::SCHEMA_COUNT; ++$index) {
            $schemas[] = sprintf(
                <<<'YAML'
                Schema%d:
                  type: object
                  required: [id]
                  properties:
                    id:
                      type: string
                      pattern: '^[a-z]+$'
                    payload:
                      type: string
                      x-vendor-note: generated
                  prefixItems:
                    - type: string
                YAML,
                $index,
            );
        }

        $components = "openapi: 3.1.0\ninfo:\n  title: Large\n  version: 1.0.0\ncomponents:\n  schemas:\n";

        foreach ($schemas as $schema) {
            $components .= $schema . "\n";
        }

        return $components;
    }
}
