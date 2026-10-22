<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Schema\Parser\YamlParser;
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
