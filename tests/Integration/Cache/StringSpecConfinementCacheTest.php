<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Cache;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;
use function sprintf;
use function strlen;

/**
 * @internal
 */
final class StringSpecConfinementCacheTest extends TestCase
{
    private const int BUILD_ITERATIONS = 10;

    private const int MIN_SPEC_BYTES = 25_000;

    private const int PATH_COUNT = 40;

    private const int SCHEMA_COUNT = 30;

    private const int CHILD_REF_GROUP_SIZE = 5;

    #[Test]
    public function string_spec_confinement_cache_reuses_entries_across_builds(): void
    {
        $spec = $this->generateYamlSpec();
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);
        $build = static fn(): OpenApiValidatorInterface => OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->withCache($cache)
            ->build();

        $build();

        $writeCountAfterWarmup = $pool->writeCount;
        $readCountAfterWarmup = $pool->readCount;

        $validators = [];
        for ($i = 0; $i < self::BUILD_ITERATIONS; ++$i) {
            $validators[] = $build();
        }

        self::assertGreaterThanOrEqual(
            self::MIN_SPEC_BYTES,
            strlen($spec),
            'Test precondition: the generated spec must be large enough to exercise the cache path.',
        );
        foreach ($validators as $validator) {
            self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);
        }

        self::assertSame(
            $writeCountAfterWarmup,
            $pool->writeCount,
            'Builds after warm-up must not write to the pool: neither the document nor the verdict may be rewritten.',
        );
        self::assertGreaterThan(
            $readCountAfterWarmup,
            $pool->readCount,
            'Builds after warm-up must read the cached document and verdict from the pool.',
        );
    }

    private function generateYamlSpec(): string
    {
        $lines = [
            'openapi: 3.1.0',
            'info:',
            '  title: String spec confinement cache spec',
            '  version: 1.0.0',
            'paths:',
        ];

        for ($i = 1; $i <= self::PATH_COUNT; ++$i) {
            $lines[] = sprintf('  /resource%d:', $i);
            $lines[] = '    get:';
            $lines[] = sprintf('      operationId: listResource%d', $i);
            $lines[] = '      responses:';
            $lines[] = "        '200':";
            $lines[] = '          description: OK';
            $lines[] = '          content:';
            $lines[] = '            application/json:';
            $lines[] = '              schema:';
            $lines[] = sprintf('                $ref: \'#/components/schemas/Schema%d\'', $i % self::SCHEMA_COUNT + 1);
        }

        $lines[] = 'components:';
        $lines[] = '  schemas:';

        for ($i = 1; $i <= self::SCHEMA_COUNT; ++$i) {
            $lines[] = sprintf('    Schema%d:', $i);
            $lines[] = '      type: object';
            $lines[] = sprintf('      description: Generated schema number %d with a description line to add realistic bulk text to the document', $i);
            $lines[] = '      required:';
            $lines[] = '        - id';
            $lines[] = '        - name';
            $lines[] = '      properties:';
            $lines[] = '        id:';
            $lines[] = '          type: integer';
            $lines[] = sprintf('          description: Identifier of schema %d used across many operations in this generated specification', $i);
            $lines[] = '        name:';
            $lines[] = '          type: string';
            $lines[] = '          minLength: 1';
            $lines[] = '          maxLength: 255';
            $lines[] = '        status:';
            $lines[] = '          type: string';
            $lines[] = '          enum:';
            $lines[] = '            - active';
            $lines[] = '            - inactive';
            $lines[] = '            - pending';
            $lines[] = '        tags:';
            $lines[] = '          type: array';
            $lines[] = '          items:';
            $lines[] = '            type: string';
            $lines[] = '        child:';
            $lines[] = sprintf('          $ref: \'#/components/schemas/Schema%d\'', $i % self::CHILD_REF_GROUP_SIZE + 1);
            $lines[] = '        createdAt:';
            $lines[] = '          type: string';
            $lines[] = '          format: date-time';
        }

        return implode("\n", $lines) . "\n";
    }
}
