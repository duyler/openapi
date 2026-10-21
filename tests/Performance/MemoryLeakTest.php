<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\FixtureLocator;
use Duyler\OpenApi\Test\Support\Psr7RequestStubFactory;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemoryLeakTest extends TestCase
{
    use Psr7RequestStubFactory;

    #[Test]
    public function no_memory_leak_on_repeated_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(FixtureLocator::path('petstore.yaml'))
            ->build();

        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 100; $i++) {
            $request = $this->createStubRequest('GET', '/pets');
            try {
                $validator->validateRequest($request);
            } catch (Exception) {
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / (float) $initialMemory * 100;

        self::assertLessThan(50, $growth, 'Memory growth should be less than 50%');
    }

    #[Test]
    public function no_memory_leak_on_schema_parsing(): void
    {
        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 10; $i++) {
            $validator = OpenApiValidatorBuilder::create()
                ->fromYamlFile(FixtureLocator::path('petstore.yaml'))
                ->build();
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / (float) $initialMemory * 100;

        self::assertLessThan(20, $growth, 'Memory growth should be less than 20%');
    }

    #[Test]
    public function pool_prevents_memory_leaks(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(FixtureLocator::path('petstore.yaml'))
            ->build();

        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 100; $i++) {
            $request = $this->createStubRequest('GET', '/pets');
            try {
                $validator->validateRequest($request);
            } catch (Exception) {
            }

            if (0 === $i % 20) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / (float) $initialMemory * 100;

        self::assertLessThan(30, $growth, 'Pool should not accumulate objects indefinitely');
    }
}
