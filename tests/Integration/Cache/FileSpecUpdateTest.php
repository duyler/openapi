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
 * P-098: cache invalidation on spec update. The validator keys its PSR-6
 * cache entry on sha256(realpath + mtime + size); a file modification that
 * changes either dimension must produce a fresh cache key so the rebuilt
 * validator picks up the new spec rather than serving the stale document.
 *
 * Each test writes a unique on-disk temp file so parallel PHPUnit workers
 * do not race on a shared fixture path. The in-memory PSR-6 pool stub
 * records every key it has seen so the test can assert that the second
 * build hit a different cache key.
 *
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

        // Bump minLength and rewrite the file with a different byte length
        // so the cache key (which incorporates size) changes deterministically
        // without depending on filesystem mtime granularity.
        $oldContents = (string) file_get_contents($tempPath);
        file_put_contents($tempPath, str_replace('minLength: 1', 'minLength: 50', $oldContents));
        clearstatcache(true, $tempPath);
        // Force a 1-second mtime bump for filesystems with 1-second mtime granularity.
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

        // Pad the spec with a trailing comment so size (and therefore the
        // derived hash) changes deterministically regardless of mtime.
        file_put_contents($tempPath, (string) file_get_contents($tempPath) . "\n# cache-bust-v2\n");
        clearstatcache(true, $tempPath);
        // Force a 1-second mtime bump so filemtime() returns a different
        // value on file systems with 1-second mtime granularity.
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

    /**
     * R3-SEC-003 / S-003 reproducer: two specs that share byte-for-byte
     * identical size AND an attacker-controlled identical mtime (via
     * `touch -r`) but differ in content must produce different cache keys.
     * A metadata-only key (path + mtime + size) collapses to the same
     * digest and silently serves a cache-poisoned document. The content
     * hash fix defeats this attack (OWASP ASVS V8.1.3, CWE-349, CWE-1023).
     */
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

        // Capture v1 mtime so v2 can be mtime-aligned with it (this is the
        // `touch -r` step from the S-003 attack timeline).
        $v1Mtime = filemtime($tempPath);
        self::assertNotFalse($v1Mtime);
        $v1Size = filesize($tempPath);
        self::assertNotFalse($v1Size);

        // Overwrite with a semantically different spec (minLength: 50 vs 99)
        // that is byte-for-byte identical in length: both digits occupy the
        // same character slot, so size is unchanged. This is the size-
        // preserving spec tampering step from the S-003 attack timeline.
        file_put_contents($tempPath, $this->renderSpec(99));
        clearstatcache(true, $tempPath);

        // Restore the original mtime to defeat metadata-only cache keys.
        touch($tempPath, $v1Mtime);
        clearstatcache(true, $tempPath);

        $v2Size = filesize($tempPath);
        self::assertNotFalse($v2Size);
        self::assertSame(
            $v1Size,
            $v2Size,
            'Test precondition: v1 and v2 must be byte-for-byte identical in size (S-003 attack assumes size preservation).',
        );
        self::assertSame(
            $v1Mtime,
            filemtime($tempPath),
            'Test precondition: v1 and v2 must share an identical mtime (S-003 attack assumes mtime preservation via touch -r).',
        );

        OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempPath)
            ->withCache($cache)
            ->build();

        $uniqueKeys = array_values(array_unique($requestedKeys));
        self::assertGreaterThanOrEqual(
            2,
            count($uniqueKeys),
            'Spec content change must produce a different cache key even when size and mtime are preserved (S-003 cache-poisoning defence, R3-SEC-003).',
        );

        // Verify semantic difference: minLength 50 (v1) accepts a 70-char
        // name, minLength 99 (v2) rejects it. The cached document must NOT
        // be returned for the v2 build (cache-miss by content-hash), so the
        // v2 validator enforces the v2 contract.
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
            'minLength: 99 (v2) must reject a 70-char name. A cache-hit returning the v1 document (minLength: 50) is a cache-poisoning breach (S-003).',
        );

        unlink($tempPath);
    }

    /**
     * Write a unique temp spec file and return its absolute path.
     */
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
     * Build a PSR-6 pool that records every requested key into $requestedKeys
     * and backs the cache with the shared $storage reference.
     *
     * @param array<string, mixed> $storage
     * @param list<string>         $requestedKeys
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
