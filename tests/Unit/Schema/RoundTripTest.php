<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema;

use Duyler\OpenApi\Schema\Parser\JsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RoundTripTest extends TestCase
{
    #[Test]
    public function round_trip_preserves_components_schemas(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Round-trip suite", "version": "1.0.0"},
    "components": {
        "schemas": {
            "User": {
                "type": "object",
                "title": "User",
                "description": "A user record",
                "required": ["id", "name"],
                "properties": {
                    "id": {"type": "integer", "minimum": 0, "exclusiveMinimum": true},
                    "name": {"type": "string", "minLength": 1, "maxLength": 100, "pattern": "^[a-zA-Z ]+$"},
                    "email": {"type": "string", "format": "email", "nullable": true},
                    "role": {"type": "string", "enum": ["admin", "user"], "default": "user"},
                    "tags": {"type": "array", "items": {"type": "string"}, "uniqueItems": true, "minItems": 0},
                    "address": {"$ref": "#/components/schemas/Address"}
                },
                "additionalProperties": false
            },
            "Address": {
                "type": "object",
                "properties": {
                    "street": {"type": "string"},
                    "city": {"type": "string"}
                }
            },
            "Pet": {
                "oneOf": [
                    {"$ref": "#/components/schemas/Cat"},
                    {"$ref": "#/components/schemas/Dog"}
                ],
                "discriminator": {"propertyName": "kind"}
            },
            "Cat": {"type": "object", "properties": {"kind": {"type": "string", "enum": ["cat"]}}},
            "Dog": {"type": "object", "properties": {"kind": {"type": "string", "enum": ["dog"]}}}
        }
    }
}
JSON;

        $first = new JsonParser()->parse($json);
        $firstJson = json_encode($first, JSON_THROW_ON_ERROR);

        $second = new JsonParser()->parse($firstJson);
        $secondJson = json_encode($second, JSON_THROW_ON_ERROR);

        self::assertSame($firstJson, $secondJson);
    }

    #[Test]
    public function round_trip_preserves_composition_and_boolean_form_keywords(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Composition", "version": "1.0.0"},
    "components": {
        "schemas": {
            "AllOfComposite": {
                "allOf": [{"type": "object"}, {"$ref": "#/components/schemas/Trait"}]
            },
            "AnyOfComposite": {
                "anyOf": [{"type": "string"}, {"type": "integer"}]
            },
            "NotSchema": {"not": {"type": "string"}},
            "IfThenElse": {
                "if": {"type": "object"},
                "then": {"required": ["name"]},
                "else": false
            },
            "ItemsTrue": {"type": "array", "items": true},
            "ItemsFalse": {"type": "array", "items": false},
            "ContainsTrue": {"contains": true},
            "UnevaluatedPropertiesFalse": {"unevaluatedProperties": false},
            "PropertyNamesFalse": {"propertyNames": false}
        }
    }
}
JSON;

        $first = new JsonParser()->parse($json);
        $firstJson = json_encode($first, JSON_THROW_ON_ERROR);

        $second = new JsonParser()->parse($firstJson);
        $secondJson = json_encode($second, JSON_THROW_ON_ERROR);

        self::assertSame($firstJson, $secondJson);
    }

    #[Test]
    public function round_trip_preserves_components_responses_headers_links_examples(): void
    {
        $json = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Components round-trip", "version": "1.0.0"},
    "components": {
        "responses": {
            "NotFound": {"description": "Not Found", "headers": {"X-Trace": {"description": "trace"}}}
        },
        "parameters": {
            "LimitParam": {"name": "limit", "in": "query", "schema": {"type": "integer"}}
        },
        "examples": {
            "UserExample": {"summary": "An example", "value": {"id": 42, "name": "John"}}
        },
        "requestBodies": {
            "UserBody": {"description": "user payload", "required": true, "content": {"application/json": {"schema": {"type": "object"}}}}
        },
        "headers": {
            "XRateLimit": {"description": "limit", "schema": {"type": "integer"}}
        },
        "securitySchemes": {
            "bearerAuth": {"type": "http", "scheme": "bearer"}
        },
        "links": {
            "UserLink": {"operationId": "getUser", "parameters": {"id": "$response.body#/id"}}
        }
    }
}
JSON;

        $first = new JsonParser()->parse($json);
        $firstJson = json_encode($first, JSON_THROW_ON_ERROR);

        $second = new JsonParser()->parse($firstJson);
        $secondJson = json_encode($second, JSON_THROW_ON_ERROR);

        self::assertSame($firstJson, $secondJson);
    }
}
