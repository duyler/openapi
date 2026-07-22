<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function strlen;

final class JsonParserTest extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonParser();
    }

    #[Test]
    public function parse_valid_openapi_3_0_schema(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "paths": {
        "/users": {
            "get": {
                "summary": "List users",
                "responses": {
                    "200": {
                        "description": "A list of users",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "type": "array",
                                    "items": {
                                        "type": "object",
                                        "properties": {
                                            "id": {"type": "integer"},
                                            "name": {"type": "string"}
                                        }
                                    }
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

        $document = $this->parser->parse($json);

        $this->assertInstanceOf(OpenApiDocument::class, $document);
        $this->assertSame('3.0.3', $document->openapi);
        $this->assertSame('Sample API', $document->info->title);
        $this->assertSame('1.0.0', $document->info->version);
    }

    #[Test]
    public function parse_valid_openapi_3_1_schema(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.1.0",
    "info": {
        "title": "Sample API",
        "version": "1.0.0",
        "contact": {
            "name": "API Support",
            "email": "support@example.com"
        }
    },
    "paths": {},
    "components": {
        "schemas": {
            "User": {
                "type": "object",
                "properties": {
                    "id": {"type": "integer"},
                    "name": {"type": "string"}
                }
            }
        }
    }
}
JSON;

        $document = $this->parser->parse($json);

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
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "servers": [
        {
            "url": "https://api.example.com/v1",
            "description": "Production server"
        },
        {
            "url": "https://staging-api.example.com/v1",
            "description": "Staging server"
        }
    ],
    "paths": {}
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->servers);
        $this->assertCount(2, $document->servers->servers);
        $this->assertSame('https://api.example.com/v1', $document->servers->servers[0]->url);
        $this->assertSame('Production server', $document->servers->servers[0]->description);
    }

    #[Test]
    public function parse_with_tags(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "tags": [
        {
            "name": "users",
            "description": "User operations"
        },
        {
            "name": "posts",
            "description": "Post operations"
        }
    ],
    "paths": {}
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->tags);
        $this->assertCount(2, $document->tags->tags);
        $this->assertSame('users', $document->tags->tags[0]->name);
        $this->assertSame('User operations', $document->tags->tags[0]->description);
    }

    #[Test]
    public function parse_with_security_requirements(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "paths": {},
    "security": [
        {
            "bearerAuth": []
        },
        {
            "apiKey": []
        }
    ]
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->security);
        $this->assertCount(2, $document->security->requirements);
        $this->assertArrayHasKey('bearerAuth', $document->security->requirements[0]);
    }

    #[Test]
    public function parse_with_external_docs(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "externalDocs": {
        "url": "https://docs.example.com",
        "description": "Find more info here"
    },
    "paths": {}
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->externalDocs);
        $this->assertSame('https://docs.example.com', $document->externalDocs->url);
        $this->assertSame('Find more info here', $document->externalDocs->description);
    }

    #[Test]
    public function parse_with_json_schema_dialect(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.1.0",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "jsonSchemaDialect": "https://json-schema.org/draft/2020-12/schema",
    "paths": {}
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $document->jsonSchemaDialect);
    }

    #[Test]
    public function parse_invalid_json(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Failed to parse JSON');

        $this->parser->parse('{"invalid": json}');
    }

    #[Test]
    public function parse_missing_openapi_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('OpenAPI version is required');

        $this->parser->parse('{"info": {"title": "Test", "version": "1.0.0"}}');
    }

    #[Test]
    public function parse_unsupported_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Unsupported OpenAPI version: 2.0.0');

        $this->parser->parse('{"openapi": "2.0.0", "info": {"title": "Test", "version": "1.0.0"}}');
    }

    #[Test]
    public function parse_missing_info_title_and_version(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Info object must have title and version');

        $this->parser->parse('{"openapi": "3.0.3", "info": {}}');
    }

    #[Test]
    public function parse_root_not_object(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('OpenAPI version is required');

        $this->parser->parse('{"info": {"title": "Test"}}');
    }

    #[Test]
    public function parse_with_parameters(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "paths": {
        "/users/{id}": {
            "get": {
                "summary": "Get user by ID",
                "parameters": [
                    {
                        "name": "id",
                        "in": "path",
                        "required": true,
                        "schema": {
                            "type": "integer"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "User found"
                    }
                }
            }
        }
    }
}
JSON;

        $document = $this->parser->parse($json);

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
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "paths": {
        "/users": {
            "post": {
                "summary": "Create user",
                "requestBody": {
                    "description": "User to create",
                    "required": true,
                    "content": {
                        "application/json": {
                            "schema": {
                                "type": "object",
                                "properties": {
                                    "name": {"type": "string"}
                                },
                                "required": ["name"]
                            }
                        }
                    }
                },
                "responses": {
                    "201": {
                        "description": "User created"
                    }
                }
            }
        }
    }
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->paths);
        $pathItem = $document->paths->paths['/users'];
        $this->assertNotNull($pathItem->post);
        $this->assertNotNull($pathItem->post->requestBody);
        $this->assertTrue($pathItem->post->requestBody->required);
        $this->assertSame('User to create', $pathItem->post->requestBody->description);
    }

    #[Test]
    public function parse_with_components_schemas(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {
        "title": "Sample API",
        "version": "1.0.0"
    },
    "paths": {
        "/users": {
            "get": {
                "responses": {
                    "200": {
                        "description": "List of users",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "type": "array",
                                    "items": {
                                        "$ref": "#/components/schemas/User"
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    },
    "components": {
        "schemas": {
            "User": {
                "type": "object",
                "properties": {
                    "id": {"type": "integer"},
                    "name": {"type": "string"}
                }
            }
        }
    }
}
JSON;

        $document = $this->parser->parse($json);

        $this->assertNotNull($document->components);
        $this->assertNotNull($document->components->schemas);
        $this->assertArrayHasKey('User', $document->components->schemas);
        $userSchema = $document->components->schemas['User'];
        $this->assertSame('object', $userSchema->type);
        $this->assertNotNull($userSchema->properties);
        $this->assertArrayHasKey('id', $userSchema->properties);
        $this->assertArrayHasKey('name', $userSchema->properties);
    }

    #[Test]
    public function parse_rejects_json_spec_exceeding_default_size_cap(): void
    {
        // Given: default JsonParser (1 MB cap) and an oversized payload.
        $parser = new JsonParser();
        $oversized = str_repeat(' ', JsonParser::DEFAULT_MAX_SPEC_BYTES + 1);

        // Then: size-cap violation surfaces with both bounds in the message.
        $this->expectException(SpecTooLargeException::class);
        $this->expectExceptionMessage((string) JsonParser::DEFAULT_MAX_SPEC_BYTES);

        // When: parse attempts to consume the payload.
        $parser->parse($oversized);
    }

    #[Test]
    public function parse_accepts_json_spec_at_exact_default_size_cap(): void
    {
        // Given: default JsonParser and a valid JSON padded with whitespace
        // so strlen equals the cap exactly (boundary case).
        $parser = new JsonParser();
        $payload = '{"openapi": "3.0.3", "info": {"title": "T", "version": "1.0.0"}, "paths": {}}';
        $padding = str_repeat(' ', JsonParser::DEFAULT_MAX_SPEC_BYTES - strlen($payload));
        $content = $payload . $padding;

        $this->assertSame(JsonParser::DEFAULT_MAX_SPEC_BYTES, strlen($content));

        // When: parse runs against the boundary-sized content.
        $document = $parser->parse($content);

        // Then: parsing succeeds and the document is constructed.
        $this->assertSame('3.0.3', $document->openapi);
    }

    #[Test]
    public function parse_rejects_json_spec_exceeding_custom_size_cap(): void
    {
        // Given: JsonParser with a tight custom cap and an oversized payload.
        $parser = new JsonParser(maxSpecBytes: 100);
        $oversized = str_repeat(' ', 101);

        // Then: the custom cap value appears in the rejection message.
        $this->expectException(SpecTooLargeException::class);
        $this->expectExceptionMessage('100');

        // When: parse attempts to consume the oversized payload.
        $parser->parse($oversized);
    }

    #[Test]
    public function parse_size_cap_does_not_affect_smaller_specs(): void
    {
        // Given: default JsonParser and a small valid OpenAPI document.
        $parser = new JsonParser();
        $json = <<<'JSON'
{
    "openapi": "3.0.3",
    "info": {"title": "Small API", "version": "1.0.0"},
    "paths": {}
}
JSON;

        // When: parse runs against the small document.
        $document = $parser->parse($json);

        // Then: the document is constructed without a size-cap exception.
        $this->assertSame('3.0.3', $document->openapi);
        $this->assertSame('Small API', $document->info->title);
    }

    #[Test]
    public function parse_size_cap_throws_before_decode_for_oversized_invalid_json(): void
    {
        // Given: JsonParser with a tight custom cap and a payload that is
        // both oversized AND invalid JSON. Size check must win so the
        // attacker never reaches json_decode (which would throw JsonException).
        $parser = new JsonParser(maxSpecBytes: 100);
        $oversized = str_repeat('x', 10_000);

        // Then: SpecTooLargeException (not JsonException / InvalidSchemaException).
        $this->expectException(SpecTooLargeException::class);

        // When: parse attempts to consume the oversized invalid payload.
        $parser->parse($oversized);
    }

    #[Test]
    public function parse_size_cap_via_builder_with_max_spec_size(): void
    {
        // Given: a JSON spec loaded via the builder with a tiny size cap
        // and an oversized JSON payload that would otherwise parse.
        $json = '{"openapi": "3.0.3", "info": {"title": "T", "version": "1.0.0"}, "paths": {}}';
        $oversized = str_repeat(' ', 101) . $json;

        // Then: the builder surfaces the size-cap exception at build time.
        $this->expectException(SpecTooLargeException::class);

        // When: build triggers parseSpec which constructs the JsonParser
        // with the configured maxSpecSizeBytes.
        OpenApiValidatorBuilder::create()
            ->fromJsonString($oversized)
            ->withMaxSpecSize(100)
            ->build();
    }
}
