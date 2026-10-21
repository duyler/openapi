<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Cache;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function implode;
use function json_encode;
use function sys_get_temp_dir;
use function str_repeat;
use function touch;
use function uniqid;
use function unlink;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class FileSpecUpdateTest extends TestCase
{
    private const string SPEC_TEMPLATE = <<<'YAML'
openapi: 3.2.0
info:
  title: Cache Invalidation API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
                  minLength: {min_length}
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function rebuild_picks_up_updated_spec_after_file_change(): void
    {
        $tempPath = $this->createTempSpec(1);
        $storage = [];
        $requestedKeys = [];

        $cache = new SchemaCache($this->buildRecordingPool($storage, $requestedKeys));

        $validatorV1 = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $shortBody = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'x'], JSON_THROW_ON_ERROR)));

        $firstOperation = $validatorV1->validateRequest($shortBody);
        self::assertSame('POST', $firstOperation->method);

        $oldContents = (string) file_get_contents($tempPath);
        file_put_contents($tempPath, str_replace('minLength: 1', 'minLength: 50', $oldContents));
        clearstatcache(true, $tempPath);
        touch($tempPath, time() + 1);

        $validatorV2 = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $tooShortNow = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'short'], JSON_THROW_ON_ERROR)));

        $rejected = false;
        try {
            $validatorV2->validateRequest($tooShortNow);
        } catch (ValidationException|Throwable) {
            $rejected = true;
        }

        self::assertTrue(
            $rejected,
            'After spec update the rebuilt validator must enforce the new minLength=50 contract.',
        );

        $uniqueKeys = array_values(array_unique($requestedKeys));
        self::assertGreaterThanOrEqual(
            2,
            count($uniqueKeys),
            'Spec file change must produce a different cache key (mtime + size hash).',
        );

        unlink($tempPath);
    }

    #[Test]
    public function cache_miss_when_spec_hash_changes(): void
    {
        $tempPath = $this->createTempSpec(1);
        $storage = [];
        $requestedKeys = [];

        $cache = new SchemaCache($this->buildRecordingPool($storage, $requestedKeys));

        OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        file_put_contents($tempPath, (string) file_get_contents($tempPath) . "\n# cache-bust-v2\n");
        clearstatcache(true, $tempPath);
        touch($tempPath, time() + 1);

        OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $uniqueKeys = array_values(array_unique($requestedKeys));

        self::assertGreaterThanOrEqual(
            2,
            count($uniqueKeys),
            sprintf(
                'Spec file change must produce a different cache key (mtime + size hash). got keys: %s',
                implode(', ', $uniqueKeys),
            ),
        );

        unlink($tempPath);
    }

    #[Test]
    public function rebuild_detects_spec_change_when_size_and_mtime_preserved(): void
    {
        $tempPath = $this->createTempSpec(50);
        $storage = [];
        $requestedKeys = [];

        $cache = new SchemaCache($this->buildRecordingPool($storage, $requestedKeys));

        OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $v1Mtime = filemtime($tempPath);
        self::assertNotFalse($v1Mtime);
        $v1Size = filesize($tempPath);
        self::assertNotFalse($v1Size);

        file_put_contents($tempPath, $this->renderSpec(99));
        clearstatcache(true, $tempPath);

        touch($tempPath, $v1Mtime);
        clearstatcache(true, $tempPath);

        $v2Size = filesize($tempPath);
        self::assertNotFalse($v2Size);
        self::assertSame(
            $v1Size,
            $v2Size,
            'Test precondition: v1 and v2 must be byte-for-byte identical in size (attack assumes size preservation).',
        );
        self::assertSame(
            $v1Mtime,
            filemtime($tempPath),
            'Test precondition: v1 and v2 must share an identical mtime (attack assumes mtime preservation via touch -r).',
        );

        OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $uniqueKeys = array_values(array_unique($requestedKeys));
        self::assertGreaterThanOrEqual(
            2,
            count($uniqueKeys),
            'Spec content change must produce a different cache key even when size and mtime are preserved (cache-poisoning defence).',
        );

        $validatorV2 = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $longNameRequest = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(
                json_encode(['name' => str_repeat('x', 70)], JSON_THROW_ON_ERROR),
            ));

        $rejected = false;
        try {
            $validatorV2->validateRequest($longNameRequest);
        } catch (ValidationException|Throwable) {
            $rejected = true;
        }

        self::assertTrue(
            $rejected,
            'minLength: 99 (v2) must reject a 70-char name. A cache-hit returning the v1 document (minLength: 50) is a cache-poisoning breach.',
        );

        unlink($tempPath);
    }

    private function createTempSpec(int $minLength): string
    {
        $path = sys_get_temp_dir() . '/duyler-spec-' . uniqid(more_entropy: true) . '.yaml';
        file_put_contents($path, $this->renderSpec($minLength));

        return $path;
    }

    private function renderSpec(int $minLength): string
    {
        return str_replace('{min_length}', (string) $minLength, self::SPEC_TEMPLATE);
    }

    /**
     * @param array<string, mixed> $storage
     * @param list<string> $requestedKeys
     */
    private function buildRecordingPool(array &$storage, array &$requestedKeys): CacheItemPoolInterface
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$storage, &$requestedKeys) {
                $requestedKeys[] = $key;
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(array_key_exists($key, $storage));
                $item->method('get')->willReturn($storage[$key] ?? null);
                $item->method('set')->willReturnCallback(static function ($value) use ($key, &$storage, $item) {
                    $storage[$key] = $value;

                    return $item;
                });
                $item->method('expiresAfter')->willReturnSelf();

                return $item;
            });

        $pool->method('save')->willReturn(true);
        $pool->method('hasItem')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $storage));

        return $pool;
    }
}
