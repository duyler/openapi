<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function hrtime;
use function implode;
use function sort;
use function sprintf;
use function strlen;

/**
 * Regression budget for issue #68: with a warm confinement verdict cache,
 * build() for a string-loaded spec must skip the ExternalRefDetector
 * re-parse, so the median cached build time on a ~300 KB spec stays well
 * below the cost of a single raw parse (the pre-fix behaviour).
 *
 * @internal
 */
final class ConfinementCacheBenchTest extends TestCase
{
    private const float MAX_CACHED_BUILD_MS = 25.0;

    private const int BUILD_ITERATIONS = 10;

    private const int MIN_SPEC_BYTES = 250_000;

    private const int PATH_COUNT = 40;

    private const int SCHEMA_COUNT = 450;

    private const int CHILD_REF_GROUP_SIZE = 5;

    #[Test]
    public function cached_string_spec_build_avoids_confinement_reparse(): void
    {
        $spec = $this->generateLargeYamlSpec();
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);
        $build = static fn(): OpenApiValidatorInterface => OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->withCache($cache)
            ->build();

        $build();

        $samples = [];
        for ($i = 0; $i < self::BUILD_ITERATIONS; ++$i) {
            $start = hrtime(true);
            $build();
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        self::assertGreaterThanOrEqual(
            self::MIN_SPEC_BYTES,
            strlen($spec),
            'Test precondition: the generated spec must be large enough for the budget to be meaningful.',
        );
        self::assertLessThan(
            self::MAX_CACHED_BUILD_MS,
            $this->median($samples),
            'Cached build() median exceeds the regression budget: the confinement verdict re-parse regression is back.',
        );
    }

    private function generateLargeYamlSpec(): string
    {
        $lines = [
            'openapi: 3.1.0',
            'info:',
            '  title: Confinement cache bench spec',
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

    /** @param list<float> $samples */
    private function median(array $samples): float
    {
        sort($samples);
        $count = count($samples);

        return $samples[(int) ($count / 2)];
    }
}
