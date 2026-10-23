<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function memory_get_usage;
use function microtime;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class DynamicRefPerformanceTest extends TestCase
{
    private const float TIME_BUDGET_MS = 100.0;

    private const float LINEARITY_LIMIT = 15.0;

    private const int MEMORY_BUDGET_BYTES = 4194304;

    #[Test]
    public function recursive_dynamic_ref_tree_at_max_depth_validates_within_budget(): void
    {
        $deep = (int) (ValidationContext::MAX_DEPTH / 2) - 2;
        $shallow = 10;
        $validator = $this->buildValidator();

        $deepMs = $this->measureMs($validator, $this->nestedInstance($deep));
        $shallowMs = $this->measureMs($validator, $this->nestedInstance($shallow));

        $memoryDelta = $this->measureMemoryDelta($validator, $this->nestedInstance($deep));

        $linearity = $deepMs / max($shallowMs, 0.001);

        self::assertLessThan(
            self::TIME_BUDGET_MS,
            $deepMs,
            sprintf('Depth %d dynamic ref tree took %.2fms, budget is %.0fms', $deep, $deepMs, self::TIME_BUDGET_MS),
        );

        self::assertLessThan(
            self::LINEARITY_LIMIT,
            $linearity,
            sprintf(
                'Deep/shallow time ratio %.1fx exceeds the baseline-progress limit of %.0fx (deep %.2fms vs shallow %.2fms)',
                $linearity,
                self::LINEARITY_LIMIT,
                $deepMs,
                $shallowMs,
            ),
        );

        self::assertLessThan(
            self::MEMORY_BUDGET_BYTES,
            $memoryDelta,
            sprintf('Depth %d dynamic ref tree used %.2fMB, budget is %.2fMB', $deep, $memoryDelta / 1048576, self::MEMORY_BUDGET_BYTES / 1048576),
        );
    }

    private function measureMs(OpenApiValidatorInterface $validator, array $instance): float
    {
        $start = microtime(true);

        for ($run = 0; $run < 5; ++$run) {
            $validator->validateSchema($instance, '#/components/schemas/Subject');
        }

        return (microtime(true) - $start) * 1000.0 / 5;
    }

    private function measureMemoryDelta(OpenApiValidatorInterface $validator, array $instance): int
    {
        $before = memory_get_usage(true);
        $validator->validateSchema($instance, '#/components/schemas/Subject');

        return memory_get_usage(true) - $before;
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'dynamic ref performance', 'version' => '1.0.0'],
            'components' => [
                'schemas' => [
                    'Subject' => [
                        '$id' => 'https://example.com/perf/tree',
                        '$dynamicAnchor' => 'node',
                        'type' => 'object',
                        'properties' => [
                            'data' => true,
                            'child' => ['$dynamicRef' => '#node'],
                        ],
                    ],
                ],
            ],
        ];

        return OpenApiValidatorBuilder::create()
            ->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))
            ->build();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function nestedInstance(int $depth): array
    {
        $instance = ['data' => 1];

        for ($level = 0; $level < $depth; ++$level) {
            $instance = ['data' => 1, 'child' => $instance];
        }

        return $instance;
    }
}
