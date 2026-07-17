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
use function implode;
use function json_encode;
use function sys_get_temp_dir;
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
