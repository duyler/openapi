<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\Internal\ExternalRefDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ExternalRefDetectorTest extends TestCase
{
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
}
