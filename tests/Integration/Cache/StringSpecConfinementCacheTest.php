<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Cache;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use DateInterval;
use DateTimeInterface;

use function array_key_exists;
use function implode;
use function sprintf;
use function strlen;

/**
 * End-to-end reproduction of issue #68: fromYamlString() + SchemaCache +
 * repeated build() calls in one process. After the warm-up build the pool
 * must serve every further build without new writes: the cached document
 * and the cached confinement verdict are both reused.
 *
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
        $pool = new SpecConfinementPool();
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

final class SpecConfinementPool implements CacheItemPoolInterface
{
    public int $readCount = 0;
    public int $writeCount = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    public function getItem(string $key): CacheItemInterface
    {
        ++$this->readCount;

        return new SpecConfinementItem($key, $this->values[$key] ?? null, array_key_exists($key, $this->values));
    }

    /** @param list<string> $keys */
    public function getItems(array $keys = []): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->getItem($key);
        }

        return $out;
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    /** @param list<string> $keys */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        ++$this->writeCount;
        $this->values[$item->getKey()] = $item->get();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class SpecConfinementItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value,
        private readonly bool $hit,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        return $this;
    }
}
