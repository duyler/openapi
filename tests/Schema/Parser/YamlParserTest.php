<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlParserTest extends TestCase
{
    private YamlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlParser();
    }

    #[Test]
    public function parse_valid_openapi_3_0_schema(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: A list of users
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  properties:
                    id:
                      type: integer
                    name:
                      type: string
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertInstanceOf(OpenApiDocument::class, $document);
        $this->assertSame('3.0.3', $document->openapi);
        $this->assertSame('Sample API', $document->info->title);
        $this->assertSame('1.0.0', $document->info->version);
    }

    #[Test]
    public function parse_valid_openapi_3_1_schema(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Sample API
  version: 1.0.0
  contact:
    name: API Support
    email: support@example.com
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertInstanceOf(OpenApiDocument::class, $document);
        $this->assertSame('3.1.0', $document->openapi);
        $this->assertNotNull($document->info->contact);
        $this->assertSame('API Support', $document->info->contact->name);
        $this->assertNotNull($document->components);
        $this->assertNotNull($document->components->schemas);
        $this->assertArrayHasKey('User', $document->components->schemas);
    }

    #[Test]
    public function parse_with_servers(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
servers:
  - url: https://api.example.com/v1
    description: Production server
  - url: https://staging-api.example.com/v1
    description: Staging server
paths: {}
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->servers);
        $this->assertCount(2, $document->servers->servers);
        $this->assertSame('https://api.example.com/v1', $document->servers->servers[0]->url);
        $this->assertSame('Production server', $document->servers->servers[0]->description);
    }

    #[Test]
    public function parse_with_tags(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
tags:
  - name: users
    description: User operations
  - name: posts
    description: Post operations
paths: {}
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->tags);
        $this->assertCount(2, $document->tags->tags);
        $this->assertSame('users', $document->tags->tags[0]->name);
        $this->assertSame('User operations', $document->tags->tags[0]->description);
    }

    #[Test]
    public function parse_with_security_requirements(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths: {}
security:
  - bearerAuth: []
  - apiKey: []
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->security);
        $this->assertCount(2, $document->security->requirements);
        $this->assertArrayHasKey('bearerAuth', $document->security->requirements[0] ?? []);
    }

    #[Test]
    public function parse_with_external_docs(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
externalDocs:
  url: https://docs.example.com
  description: Find more info here
paths: {}
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->externalDocs);
        $this->assertSame('https://docs.example.com', $document->externalDocs->url);
        $this->assertSame('Find more info here', $document->externalDocs->description);
    }

    #[Test]
    public function parse_invalid_yaml(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Failed to parse YAML');

        $this->parser->parse('invalid: yaml: content:');
    }

    #[Test]
    public function parse_missing_openapi_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('OpenAPI version is required');

        $this->parser->parse("info:\n  title: Test\n  version: 1.0.0");
    }

    #[Test]
    public function parse_unsupported_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Unsupported OpenAPI version: 2.0.0');

        $this->parser->parse("openapi: 2.0.0\ninfo:\n  title: Test\n  version: 1.0.0");
    }

    #[Test]
    public function parse_missing_info_title_and_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Info object must have title and version');

        $this->parser->parse("openapi: 3.0.3\ninfo: {}");
    }

    #[Test]
    public function parse_root_not_array(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('OpenAPI version is required');

        $this->parser->parse('- not\n- an\n- array');
    }

    #[Test]
    public function parse_with_parameters(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      summary: Get user by ID
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: User found
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->paths);
        $this->assertArrayHasKey('/users/{id}', $document->paths->paths);
        $pathItem = $document->paths->paths['/users/{id}'];
        $this->assertNotNull($pathItem->get);
        $this->assertNotNull($pathItem->get->parameters);
        $this->assertCount(1, $pathItem->get->parameters->parameters);
        $this->assertSame('id', $pathItem->get->parameters->parameters[0]->name);
        $this->assertSame('path', $pathItem->get->parameters->parameters[0]->in);
        $this->assertTrue($pathItem->get->parameters->parameters[0]->required);
    }

    #[Test]
    public function parse_with_request_body(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths:
  /users:
    post:
      summary: Create user
      requestBody:
        description: User to create
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
              required:
                - name
      responses:
        '201':
          description: User created
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->paths);
        $pathItem = $document->paths->paths['/users'];
        $this->assertNotNull($pathItem->post);
        $this->assertNotNull($pathItem->post->requestBody);
        $this->assertTrue($pathItem->post->requestBody->required);
        $this->assertSame('User to create', $pathItem->post->requestBody->description);
    }

    #[Test]
    public function parse_with_webhooks(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Sample API
  version: 1.0.0
paths: {}
webhooks:
  newPet:
    post:
      requestBody:
        description: Information about a new pet
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
      responses:
        '201':
          description: Pet created
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->webhooks);
        $this->assertArrayHasKey('newPet', $document->webhooks->webhooks);
        $this->assertNotNull($document->webhooks->webhooks['newPet']->post);
    }

    #[Test]
    public function parse_with_discriminator(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
components:
  schemas:
    Pet:
      type: object
      required:
        - petType
      properties:
        petType:
          type: string
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
    Cat:
      type: object
      properties:
        name:
          type: string
    Dog:
      type: object
      properties:
        name:
          type: string
paths: {}
YAML;

        $document = $this->parser->parse($yaml);

        $this->assertNotNull($document->components);
        $this->assertNotNull($document->components->schemas);
        $petSchema = $document->components->schemas['Pet'];
        $this->assertNotNull($petSchema->discriminator);
        $this->assertSame('petType', $petSchema->discriminator->propertyName);
        $this->assertNotNull($petSchema->discriminator->mapping);
        $this->assertArrayHasKey('cat', $petSchema->discriminator->mapping);
    }
}
