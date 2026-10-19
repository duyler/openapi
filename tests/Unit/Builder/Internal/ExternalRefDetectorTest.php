<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\Internal\ExternalRefDetector;
use Duyler\OpenApi\Cache\ConfinementVerdict;
use Duyler\OpenApi\Cache\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use DateInterval;
use DateTimeInterface;

use function array_key_exists;
use function str_ends_with;

/**
 * @internal
 */
final class ExternalRefDetectorTest extends TestCase
{
    private const string GUILTY_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Guilty spec with external ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Passwd:
      $ref: 'file:///etc/passwd'
YAML;

    #[Test]
    public function assert_confinement_passes_when_allowed_root_is_set(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: External ref with allowed root
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      $ref: 'shared/bar.yaml'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
            externalRefAllowedRoot: '/var/specs',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_passes_when_spec_content_is_null_file_loaded(): void
    {
        $detector = new ExternalRefDetector(new BuilderConfig(
            specPath: '/var/specs/openapi.yaml',
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_passes_when_string_spec_has_no_external_ref(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Only internal refs
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      type: object
    Bar:
      $ref: '#/components/schemas/Foo'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_fail_closed_on_yaml_string_spec_with_external_ref(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: External ref without allowed root
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      $ref: 'shared/bar.yaml'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        try {
            $detector->assertConfinement();
            self::fail('External $ref without allowedRoot must throw BuilderException (fail-closed).');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('shared/bar.yaml', $e->getMessage());
        }
    }

    #[Test]
    public function assert_confinement_fail_closed_on_json_string_spec_with_external_ref(): void
    {
        $json = <<<'JSON'
{
  "openapi": "3.2.0",
  "info": { "title": "x", "version": "1.0.0" },
  "paths": {},
  "components": {
    "schemas": {
      "Foo": { "$ref": "shared/bar.json" }
    }
  }
}
JSON;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $json,
            specType: 'json',
        ));

        try {
            $detector->assertConfinement();
            self::fail('External $ref in JSON spec without allowedRoot must throw BuilderException (fail-closed).');
        } catch (BuilderException $e) {
            self::assertStringContainsString('shared/bar.json', $e->getMessage());
        }
    }

    #[Test]
    public function assert_confinement_fail_closed_on_file_scheme_external_ref(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: file:// scheme ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Passwd:
      $ref: 'file:///etc/passwd'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        try {
            $detector->assertConfinement();
            self::fail('file:// external $ref without allowedRoot must throw.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function assert_confinement_fail_closed_on_discriminator_mapping_to_external_ref(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: discriminator mapping to external ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      properties:
        petType:
          type: string
      discriminator:
        propertyName: petType
        mapping:
          cat: 'file:///etc/passwd'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Cat:
      type: object
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        try {
            $detector->assertConfinement();
            self::fail('discriminator mapping to external $ref must throw.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function assert_confinement_fail_closed_on_discriminator_default_mapping_to_external_ref(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: discriminator defaultMapping to external ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      discriminator:
        defaultMapping: 'file:///etc/passwd'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        try {
            $detector->assertConfinement();
            self::fail('discriminator defaultMapping to external $ref must throw.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function assert_confinement_detects_external_ref_at_arbitrary_depth(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Nested external ref
  version: 1.0.0
paths: {}
components:
  schemas:
    L0:
      type: object
      properties:
        c1:
          type: object
          properties:
            c2:
              type: object
              properties:
                c3:
                  $ref: 'file:///etc/passwd'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('file:///etc/passwd');

        $detector->assertConfinement();
    }

    #[Test]
    public function assert_confinement_silently_passes_on_unparseable_yaml(): void
    {
        $yaml = "openapi: 3.2.0\n  - broken indent\n - also broken";

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_silently_passes_on_yaml_parse_exception(): void
    {
        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: "openapi: 'unclosed single quote",
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_passes_on_non_string_discriminator_mapping_value(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Non-string discriminator mapping value
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      discriminator:
        propertyName: petType
        mapping:
          int: 123
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_silently_passes_on_unparseable_json(): void
    {
        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: '{"openapi":}',
            specType: 'json',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_silently_passes_on_invalid_utf8_json(): void
    {
        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: "\xff\xfe",
            specType: 'json',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_silently_passes_when_yaml_exceeds_max_spec_size(): void
    {
        $hugeYaml = "openapi: 3.2.0\ninfo:\n  title: " . str_repeat('a', 5000) . "\npaths: {}\n";

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $hugeYaml,
            specType: 'yaml',
            maxSpecSizeBytes: 64,
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_internal_ref_does_not_trigger(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: internal refs only
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      type: object
    Bar:
      $ref: '#/components/schemas/Foo'
    Baz:
      discriminator:
        defaultMapping: '#/components/schemas/Foo'
        mapping:
          x: '#/components/schemas/Foo'
YAML;

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $detector->assertConfinement();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_confinement_security_anti_test_external_ref_outside_allowed_root_throws(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Path traversal attempt
  version: 1.0.0
paths: {}
components:
  schemas:
    Passwd:
      $ref: '../../../etc/passwd'
YAML;

        // The ExternalRefDetector's role is to fail-closed when externalRefAllowedRoot is null.
        // When allowedRoot IS set, the FileExternalRefResolver (separate class) enforces the boundary at resolution time.
        // This test pins the detector's contract: no allowedRoot + external $ref = exception, regardless of $ref shape.
        $detectorWithoutRoot = new ExternalRefDetector(new BuilderConfig(
            specContent: $yaml,
            specType: 'yaml',
        ));

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('../../../etc/passwd');

        $detectorWithoutRoot->assertConfinement();
    }

    #[Test]
    public function assert_confinement_writes_clean_verdict_on_first_call(): void
    {
        $pool = new ConfinementCountingPool();

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: $this->cleanSpecYaml('Clean verdict'),
            specType: 'yaml',
            cache: new SchemaCache($pool),
        ));

        $detector->assertConfinement();

        $confinementKeys = $pool->confinementKeys();
        self::assertCount(1, $confinementKeys, 'Exactly one confinement key must be persisted after the first call.');
        self::assertSame(1, $pool->writeCount);
        self::assertInstanceOf(ConfinementVerdict::class, $pool->value($confinementKeys[0]));
    }

    #[Test]
    public function assert_confinement_returns_from_cache_on_second_call(): void
    {
        $pool = new ConfinementCountingPool();
        $cache = new SchemaCache($pool);
        $specContent = $this->cleanSpecYaml('Cache hit verdict');

        $firstDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $specContent,
            specType: 'yaml',
            cache: $cache,
        ));

        $firstDetector->assertConfinement();

        $readCountAfterFirstCall = $pool->readCount;
        $writeCountAfterFirstCall = $pool->writeCount;

        $secondDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $specContent,
            specType: 'yaml',
            cache: $cache,
        ));

        $secondDetector->assertConfinement();

        self::assertGreaterThan($readCountAfterFirstCall, $pool->readCount, 'Second call must read the verdict from the pool.');
        self::assertSame($writeCountAfterFirstCall, $pool->writeCount, 'Second call must not rewrite the verdict.');
    }

    #[Test]
    public function assert_confinement_does_not_cache_guilty_verdict(): void
    {
        $pool = new ConfinementCountingPool();

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: self::GUILTY_YAML,
            specType: 'yaml',
            cache: new SchemaCache($pool),
        ));

        try {
            $detector->assertConfinement();
            self::fail('Guilty spec must throw BuilderException.');
        } catch (BuilderException $exception) {
            self::assertStringContainsString('file:///etc/passwd', $exception->getMessage());
        }

        self::assertSame([], $pool->confinementKeys(), 'Guilty verdict must never be written to the pool.');
        self::assertSame(0, $pool->writeCount);
    }

    #[Test]
    public function assert_confinement_guilty_verdict_throws_again_on_second_call(): void
    {
        $pool = new ConfinementCountingPool();

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: self::GUILTY_YAML,
            specType: 'yaml',
            cache: new SchemaCache($pool),
        ));

        try {
            $detector->assertConfinement();
            self::fail('First call on the guilty spec must throw BuilderException.');
        } catch (BuilderException $exception) {
            self::assertStringContainsString('file:///etc/passwd', $exception->getMessage());
        }

        try {
            $detector->assertConfinement();
            self::fail('Second call on the guilty spec must throw BuilderException again.');
        } catch (BuilderException $exception) {
            self::assertStringContainsString('file:///etc/passwd', $exception->getMessage());
        }

        self::assertSame(0, $pool->writeCount, 'Guilty verdict must not be cached between the calls.');
    }

    #[Test]
    public function assert_confinement_uses_content_hash_in_key(): void
    {
        $pool = new ConfinementCountingPool();
        $cache = new SchemaCache($pool);

        $firstDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $this->cleanSpecYaml('Content hash first'),
            specType: 'yaml',
            cache: $cache,
        ));

        $secondDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $this->cleanSpecYaml('Content hash second'),
            specType: 'yaml',
            cache: $cache,
        ));

        $firstDetector->assertConfinement();
        $secondDetector->assertConfinement();

        self::assertCount(2, $pool->confinementKeys(), 'Different spec content must produce different confinement keys.');
    }

    #[Test]
    public function assert_confinement_key_includes_spec_limits_fingerprint(): void
    {
        $pool = new ConfinementCountingPool();
        $cache = new SchemaCache($pool);
        $specContent = $this->cleanSpecYaml('Fingerprint limits');

        $defaultLimitsDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $specContent,
            specType: 'yaml',
            cache: $cache,
        ));

        $customLimitsDetector = new ExternalRefDetector(new BuilderConfig(
            specContent: $specContent,
            specType: 'yaml',
            cache: $cache,
            maxSpecDepth: 50,
        ));

        $defaultLimitsDetector->assertConfinement();
        $customLimitsDetector->assertConfinement();

        self::assertCount(2, $pool->confinementKeys(), 'maxSpecDepth change must produce a different confinement key.');
    }

    #[Test]
    public function assert_confinement_skips_cache_when_allowed_root_is_set(): void
    {
        $pool = new ConfinementCountingPool();

        $detector = new ExternalRefDetector(new BuilderConfig(
            specContent: self::GUILTY_YAML,
            specType: 'yaml',
            externalRefAllowedRoot: '/var/specs',
            cache: new SchemaCache($pool),
        ));

        $detector->assertConfinement();

        self::assertSame(0, $pool->readCount, 'Inactive guard must not read from the pool.');
        self::assertSame(0, $pool->writeCount, 'Inactive guard must not write to the pool.');
    }

    #[Test]
    public function assert_confinement_skips_cache_when_spec_content_is_null(): void
    {
        $pool = new ConfinementCountingPool();

        $detector = new ExternalRefDetector(new BuilderConfig(
            specPath: '/var/specs/openapi.yaml',
            specType: 'yaml',
            cache: new SchemaCache($pool),
        ));

        $detector->assertConfinement();

        self::assertSame(0, $pool->readCount, 'File-loaded spec must not read from the pool.');
        self::assertSame(0, $pool->writeCount, 'File-loaded spec must not write to the pool.');
    }

    private function cleanSpecYaml(string $title): string
    {
        return <<<YAML
openapi: 3.2.0
info:
  title: {$title}
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      type: object
    Bar:
      \$ref: '#/components/schemas/Foo'
YAML;
    }
}

final class ConfinementCountingPool implements CacheItemPoolInterface
{
    public int $readCount = 0;
    public int $writeCount = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    /** @return list<string> */
    public function confinementKeys(): array
    {
        $keys = [];
        foreach (array_keys($this->values) as $key) {
            if (str_ends_with($key, ExternalRefDetector::CONFINEMENT_KEY_SUFFIX)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function value(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function getItem(string $key): CacheItemInterface
    {
        ++$this->readCount;

        return new ConfinementCountingItem($key, $this->values[$key] ?? null, array_key_exists($key, $this->values));
    }

    /** @param list<string> $keys */
    public function getItems(array $keys = []): array
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
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

final class ConfinementCountingItem implements CacheItemInterface
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
