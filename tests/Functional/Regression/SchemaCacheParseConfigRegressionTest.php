<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_unique;
use function array_values;
use function str_ends_with;
use function str_starts_with;

/**
 * Regression suite for R4-SEC-008 / R4-TEST-011: the SchemaCache key
 * must incorporate the parse-config fingerprint so a caller that
 * tightens any of maxSpecSizeBytes / maxSpecDepth / externalRefMaxBytes
 * never silently receives a document cached under looser limits.
 *
 * Anti-test: removing the buildParseConfigFingerprint() input makes
 * the cache hit succeed in test 1 (caller B gets the cached document
 * even though its tighter size limit would otherwise reject the spec)
 * and makes the keys produced in tests 2 and 3 identical.
 *
 * Unlike the inline OpenApiValidatorBuilderCacheKeyTest that focuses
 * on maxSpecDepth and externalRefAllowedRoot, this suite exercises
 * the maxSpecSizeBytes and externalRefMaxBytes dimensions of the
 * fingerprint.
 *
 * @internal
 */
final class SchemaCacheParseConfigRegressionTest extends TestCase
{
    private const string SMALL_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
YAML;

    private const string CONFINEMENT_KEY_SUFFIX = '_confinement';

    #[Test]
    public function stricter_max_spec_size_with_shared_cache_forces_reject_on_cache_miss(): void
    {
        $sharedPool = new Psr6ArrayCacheStub();
        $sharedCache = new SchemaCache($sharedPool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SMALL_SPEC)
            ->withCache($sharedCache)
            ->withMaxSpecSize(1_048_576)
            ->build();

        $poisoningRejected = false;
        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString(self::SMALL_SPEC)
                ->withCache($sharedCache)
                ->withMaxSpecSize(1)
                ->build();
        } catch (SpecTooLargeException) {
            $poisoningRejected = true;
        }

        self::assertTrue(
            $poisoningRejected,
            'Caller B with stricter maxSpecSize=1 must NOT receive the document cached under '
            . 'maxSpecSize=1048576. The fingerprint-based cache key must force a re-parse that '
            . 'rejects the (here) oversized spec (R4-SEC-008 size poisoning defence).',
        );
    }

    #[Test]
    public function max_spec_size_change_produces_distinct_cache_keys(): void
    {
        $keysA = $this->captureKeys(fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withMaxSpecSize(100_000));
        $keysB = $this->captureKeys(fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withMaxSpecSize(50_000));

        self::assertNotSame($keysA, $keysB);
        self::assertCount(1, $keysA);
        self::assertCount(1, $keysB);
    }

    #[Test]
    public function external_ref_max_bytes_change_produces_distinct_cache_keys(): void
    {
        $keysA = $this->captureKeys(
            static fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withExternalRefMaxBytes(10_000_000),
        );
        $keysB = $this->captureKeys(
            static fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withExternalRefMaxBytes(1_000_000),
        );

        self::assertNotSame($keysA, $keysB);
        self::assertCount(1, $keysA);
        self::assertCount(1, $keysB);
    }

    #[Test]
    public function identical_parse_config_reuses_same_cache_key(): void
    {
        $keysA = $this->captureKeys(
            static fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withMaxSpecSize(1_000_000)
                ->withMaxSpecDepth(50)
                ->withExternalRefMaxBytes(5_000_000),
        );
        $keysB = $this->captureKeys(
            static fn(OpenApiValidatorBuilder $b): OpenApiValidatorBuilder => $b->withMaxSpecSize(1_000_000)
                ->withMaxSpecDepth(50)
                ->withExternalRefMaxBytes(5_000_000),
        );

        self::assertSame($keysA, $keysB, 'Identical parse-config must produce identical cache keys.');
    }

    /**
     * @param callable(OpenApiValidatorBuilder): OpenApiValidatorBuilder $configure
     *
     * @return list<string>
     */
    private function captureKeys(callable $configure): array
    {
        $pool = new Psr6ArrayCacheStub();

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SMALL_SPEC)
            ->withCache(new SchemaCache($pool));

        $configure($builder)->build();

        $unique = array_values(array_unique($pool->capturedKeys()));
        $contentKeys = array_values(array_filter(
            $unique,
            static fn(string $key): bool => str_starts_with($key, 'openapi_spec_content_')
                && !str_ends_with($key, self::CONFINEMENT_KEY_SUFFIX),
        ));

        return $contentKeys;
    }
}
