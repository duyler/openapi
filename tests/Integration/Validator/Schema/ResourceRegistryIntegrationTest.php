<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Schema\Exception\DuplicateSchemaIdException;
use Duyler\OpenApi\Validator\Schema\Exception\InvalidAnchorException;
use Duyler\OpenApi\Validator\Schema\Exception\SchemaIdFragmentException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ResourceRegistryIntegrationTest extends TestCase
{
    #[Test]
    public function registry_is_filled_for_parsed_document(): void
    {
        $document = new YamlParser()->parse($this->specification());
        $resolver = new RefResolver();

        $registry = $resolver->resourceRegistry($document);

        self::assertSame(
            $registry,
            $resolver->resourceRegistry($document),
        );
        self::assertSame(
            $document->components?->schemas['User'],
            $registry->findByUri('https://api.example.com/spec/schemas/user.json'),
        );
        self::assertSame(
            $document->components?->schemas['User']?->properties['pet'],
            $registry->findByUri('https://api.example.com/spec/schemas/pet.json'),
        );
        self::assertSame(
            $document->components?->schemas['User']?->defs['Role'],
            $registry->findByUri('https://api.example.com/spec/schemas/definitions/role.json'),
        );
        self::assertSame(
            $document->components?->schemas['User'],
            $registry->findByAnchor('user-anchor'),
        );
        self::assertSame(
            $document->components?->schemas['User']?->properties['pet'],
            $registry->findByAnchor('pet-anchor'),
        );
        self::assertSame(
            $document->components?->schemas['User'],
            $registry->findDynamicAnchor('user-dynamic'),
        );
        self::assertSame(
            $document->components?->schemas['User']?->defs['Role'],
            $registry->findDynamicAnchor('role-dynamic'),
        );
    }

    #[Test]
    public function build_fails_for_duplicate_schema_id(): void
    {
        $this->expectException(DuplicateSchemaIdException::class);
        $this->expectExceptionMessage('https://api.example.com/spec/schemas/dup.json');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: '3.2.0'
info:
  title: Duplicate Id Probe
  version: '1.0.0'
components:
  schemas:
    First:
      $id: 'https://api.example.com/spec/schemas/dup.json'
      type: object
    Second:
      $id: 'https://api.example.com/spec/schemas/dup.json'
      type: string
YAML)
            ->build();
    }

    #[Test]
    public function build_fails_for_schema_id_with_fragment(): void
    {
        $this->expectException(SchemaIdFragmentException::class);
        $this->expectExceptionMessage('#fragment');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: '3.2.0'
info:
  title: Fragmented Id Probe
  version: '1.0.0'
components:
  schemas:
    Fragmented:
      $id: 'https://api.example.com/spec/schemas/x.json#fragment'
      type: object
YAML)
            ->build();
    }

    #[Test]
    public function build_fails_for_invalid_anchor(): void
    {
        $this->expectException(InvalidAnchorException::class);
        $this->expectExceptionMessage('1bad');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: '3.2.0'
info:
  title: Invalid Anchor Probe
  version: '1.0.0'
components:
  schemas:
    Anchored:
      $anchor: '1bad'
      type: object
YAML)
            ->build();
    }

    #[Test]
    public function build_fails_for_invalid_dynamic_anchor(): void
    {
        $this->expectException(InvalidAnchorException::class);
        $this->expectExceptionMessage('bad/slash');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: '3.2.0'
info:
  title: Invalid Dynamic Anchor Probe
  version: '1.0.0'
components:
  schemas:
    Anchored:
      $dynamicAnchor: 'bad/slash'
      type: object
YAML)
            ->build();
    }

    #[Test]
    public function valid_document_builds_successfully_with_registry_forced(): void
    {
        $document = new YamlParser()->parse($this->specification());
        $resolver = new RefResolver();

        OpenApiValidatorBuilder::create()
            ->fromYamlString($this->specification())
            ->build();

        $registry = $resolver->resourceRegistry($document);

        self::assertSame(
            $document->components?->schemas['User'],
            $registry->findByUri('https://api.example.com/spec/schemas/user.json'),
        );
    }

    private function specification(): string
    {
        return <<<'YAML'
openapi: '3.2.0'
$self: 'https://api.example.com/spec/openapi.json'
info:
  title: Resource Registry Probe
  version: '1.0.0'
components:
  schemas:
    User:
      $id: 'schemas/user.json'
      $anchor: 'user-anchor'
      $dynamicAnchor: 'user-dynamic'
      type: object
      properties:
        pet:
          $id: 'pet.json'
          $anchor: 'pet-anchor'
          type: object
      $defs:
        Role:
          $id: 'definitions/role.json'
          $dynamicAnchor: 'role-dynamic'
          type: string
YAML;
    }
}
