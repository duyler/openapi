<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * STRING-SPEC-FAILOPEN: specs loaded via fromYamlString() /
 * fromJsonString() must fail closed at build() time when the spec
 * contains an external `$ref` and withExternalRefAllowedRoot() has
 * not been called. Without this guard the builtin FileExternalRefResolver
 * runs with allowedRoot = null, which means assertPathWithinAllowedRoot
 * is a no-op and an attacker-controlled spec can read arbitrary files
 * via `file:///...` or relative-path `$ref` values.
 *
 * @internal
 */
final class OpenApiValidatorBuilderStringSpecFailClosedTest extends TestCase
{
    private ?string $tempRoot = null;

    protected function tearDown(): void
    {
        if (null !== $this->tempRoot && is_dir($this->tempRoot)) {
            $this->removeDirectory($this->tempRoot);
        }
    }

    #[Test]
    public function string_spec_with_external_ref_without_allowed_root_throws_builder_exception(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with external ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      $ref: 'shared/bar.yaml'
YAML;

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        try {
            $builder->build();

            self::fail('fromYamlString spec with external $ref must throw BuilderException at build() time.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('shared/bar.yaml', $e->getMessage());
        }
    }

    #[Test]
    public function string_spec_with_external_ref_and_allowed_root_resolves(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'string_spec_failclosed_' . uniqid('', true);
        $this->tempRoot = $root;
        mkdir($root . '/shared', 0700, true);

        file_put_contents(
            $root . '/shared/bar.yaml',
            <<<'YAML'
BarSchema:
  type: object
  required: [id]
  properties:
    id:
      type: integer
YAML,
        );

        $yaml = sprintf(
            <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with external ref and allowed root
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      $ref: '%s/shared/bar.yaml#/BarSchema'
YAML,
            $root,
        );

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withExternalRefAllowedRoot($root)
            ->build();

        $validator->validateSchema(['id' => 42], '#/components/schemas/Foo');

        self::assertSame('3.2.0', $validator->getDocument()->openapi);
    }

    #[Test]
    public function string_spec_without_external_ref_builds_without_allowed_root(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with only internal ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Foo:
      type: object
      required: [id]
      properties:
        id:
          type: integer
    Bar:
      $ref: '#/components/schemas/Foo'
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        self::assertSame('3.2.0', $validator->getDocument()->openapi);
    }

    #[Test]
    public function string_spec_with_file_scheme_external_ref_throws(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with file:// ref
  version: 1.0.0
paths: {}
components:
  schemas:
    Passwd:
      $ref: 'file:///etc/passwd'
YAML;

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        try {
            $builder->build();

            self::fail('fromYamlString spec with file:// $ref must throw BuilderException at build() time.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function string_spec_with_discriminator_mapping_to_external_ref_throws(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with discriminator mapping external ref
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
      required: [petType]
      properties:
        petType:
          type: string
          enum: [cat]
YAML;

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        try {
            $builder->build();

            self::fail('fromYamlString spec with discriminator mapping to external ref must throw BuilderException at build() time.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function string_spec_with_discriminator_default_mapping_to_external_ref_throws(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: String spec with discriminator defaultMapping external ref
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
        defaultMapping: 'file:///etc/passwd'
YAML;

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        try {
            $builder->build();

            self::fail('fromYamlString spec with discriminator defaultMapping to external ref must throw BuilderException at build() time.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('file:///etc/passwd', $e->getMessage());
        }
    }

    #[Test]
    public function string_json_spec_with_external_ref_throws_builder_exception(): void
    {
        $json = <<<'JSON'
{
  "openapi": "3.2.0",
  "info": { "title": "JSON spec with external ref", "version": "1.0.0" },
  "paths": {},
  "components": {
    "schemas": {
      "Foo": { "$ref": "shared/bar.json" }
    }
  }
}
JSON;

        $builder = OpenApiValidatorBuilder::create()
            ->fromJsonString($json);

        try {
            $builder->build();

            self::fail('fromJsonString spec with external $ref must throw BuilderException at build() time.');
        } catch (BuilderException $e) {
            self::assertStringContainsString('externalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('withExternalRefAllowedRoot', $e->getMessage());
            self::assertStringContainsString('shared/bar.json', $e->getMessage());
        }
    }

    #[Test]
    public function string_json_spec_with_external_ref_beyond_max_spec_depth_throws_spec_too_large(): void
    {
        $json = <<<'JSON'
{
  "openapi": "3.2.0",
  "info": { "title": "x", "version": "1.0.0" },
  "paths": {},
  "components": {
    "schemas": {
      "L0": {
        "type": "object",
        "properties": {
          "c1": {
            "type": "object",
            "properties": {
              "c2": {
                "type": "object",
                "properties": {
                  "c3": { "$ref": "file:///etc/passwd" }
                }
              }
            }
          }
        }
      }
    }
  }
}
JSON;

        $builder = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->withMaxSpecDepth(6);

        $this->expectException(SpecTooLargeException::class);
        $this->expectExceptionMessage('Spec nesting depth of');

        $builder->build();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
