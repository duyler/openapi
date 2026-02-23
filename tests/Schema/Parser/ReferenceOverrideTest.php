<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
#[CoversClass(Parameter::class)]
#[CoversClass(Response::class)]
#[CoversClass(RefResolver::class)]
#[CoversClass(JsonParser::class)]
final class ReferenceOverrideTest extends TestCase
{
    private JsonParser $parser;
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->parser = new JsonParser();
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function schema_reference_can_override_description(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"schemas":{"User":{"type":"object","description":"Original description"}}},"paths":{"/test":{"get":{"responses":{"200":{"content":{"application/json":{"schema":{"$ref":"#/components/schemas/User","description":"Override description"}}}}}}}}}';

        $document = $this->parser->parse($json);

        $schema = $document->paths?->paths['/test']->get?->responses?->responses['200']->content?->mediaTypes['application/json']->schema;

        self::assertNotNull($schema);
        self::assertSame('#/components/schemas/User', $schema->ref);
        self::assertSame('Override description', $schema->refDescription);
    }

    #[Test]
    public function schema_reference_can_override_summary(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"schemas":{"User":{"type":"object","title":"Original title"}}},"paths":{"/test":{"get":{"responses":{"200":{"content":{"application/json":{"schema":{"$ref":"#/components/schemas/User","summary":"Override summary"}}}}}}}}}';

        $document = $this->parser->parse($json);

        $schema = $document->paths?->paths['/test']->get?->responses?->responses['200']->content?->mediaTypes['application/json']->schema;

        self::assertNotNull($schema);
        self::assertSame('#/components/schemas/User', $schema->ref);
        self::assertSame('Override summary', $schema->refSummary);
    }

    #[Test]
    public function parameter_reference_can_override_description(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"parameters":{"Limit":{"name":"limit","in":"query","description":"Original description","schema":{"type":"integer"}}}},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/Limit","description":"Override description"}],"responses":{"200":{"description":"OK"}}}}}}';

        $document = $this->parser->parse($json);

        $param = $document->paths?->paths['/test']->get?->parameters?->parameters[0];

        self::assertNotNull($param);
        self::assertSame('#/components/parameters/Limit', $param->ref);
        self::assertSame('Override description', $param->refDescription);
    }

    #[Test]
    public function parameter_reference_can_override_summary(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"parameters":{"Limit":{"name":"limit","in":"query","schema":{"type":"integer"}}}},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/Limit","summary":"Override summary"}],"responses":{"200":{"description":"OK"}}}}}}';

        $document = $this->parser->parse($json);

        $param = $document->paths?->paths['/test']->get?->parameters?->parameters[0];

        self::assertNotNull($param);
        self::assertSame('#/components/parameters/Limit', $param->ref);
        self::assertSame('Override summary', $param->refSummary);
    }

    #[Test]
    public function response_reference_can_override_description(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"responses":{"Success":{"description":"Original description"}}},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success","description":"Override description"}}}}}}';

        $document = $this->parser->parse($json);

        $response = $document->paths?->paths['/test']->get?->responses?->responses['200'];

        self::assertNotNull($response);
        self::assertSame('#/components/responses/Success', $response->ref);
        self::assertSame('Override description', $response->refDescription);
    }

    #[Test]
    public function response_reference_can_override_summary(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"responses":{"Success":{"summary":"Original summary","description":"Some description"}}},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success","summary":"Override summary"}}}}}}';

        $document = $this->parser->parse($json);

        $response = $document->paths?->paths['/test']->get?->responses?->responses['200'];

        self::assertNotNull($response);
        self::assertSame('#/components/responses/Success', $response->ref);
        self::assertSame('Override summary', $response->refSummary);
    }

    #[Test]
    public function reference_without_override_works(): void
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"schemas":{"User":{"type":"object"}}},"paths":{"/test":{"get":{"responses":{"200":{"content":{"application/json":{"schema":{"$ref":"#/components/schemas/User"}}}}}}}}}';

        $document = $this->parser->parse($json);

        $schema = $document->paths?->paths['/test']->get?->responses?->responses['200']->content?->mediaTypes['application/json']->schema;

        self::assertNotNull($schema);
        self::assertSame('#/components/schemas/User', $schema->ref);
        self::assertNull($schema->refSummary);
        self::assertNull($schema->refDescription);
    }

    #[Test]
    public function resolver_applies_schema_description_override(): void
    {
        $document = $this->createDocumentWithSchema();
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refDescription: 'Override description',
        );

        $resolved = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Override description', $resolved->description);
    }

    #[Test]
    public function resolver_applies_schema_summary_override_as_title(): void
    {
        $document = $this->createDocumentWithSchema();
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'Override title',
        );

        $resolved = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Override title', $resolved->title);
    }

    #[Test]
    public function resolver_applies_parameter_description_override(): void
    {
        $document = $this->createDocumentWithParameter();
        $parameter = new Parameter(
            ref: '#/components/parameters/Limit',
            refDescription: 'Override description',
        );

        $resolved = $this->resolver->resolveParameterWithOverride($parameter, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Override description', $resolved->description);
    }

    #[Test]
    public function resolver_applies_response_description_override(): void
    {
        $document = $this->createDocumentWithResponse();
        $response = new Response(
            ref: '#/components/responses/Success',
            refDescription: 'Override description',
        );

        $resolved = $this->resolver->resolveResponseWithOverride($response, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Override description', $resolved->description);
    }

    #[Test]
    public function resolver_applies_response_summary_override(): void
    {
        $document = $this->createDocumentWithResponse();
        $response = new Response(
            ref: '#/components/responses/Success',
            refSummary: 'Override summary',
        );

        $resolved = $this->resolver->resolveResponseWithOverride($response, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Override summary', $resolved->summary);
    }

    #[Test]
    public function resolver_returns_same_schema_if_no_ref(): void
    {
        $document = $this->createDocumentWithSchema();
        $schema = new Schema(
            type: 'string',
            description: 'Original description',
        );

        $resolved = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertSame($schema, $resolved);
    }

    #[Test]
    public function resolver_returns_same_parameter_if_no_ref(): void
    {
        $document = $this->createDocumentWithParameter();
        $parameter = new Parameter(
            name: 'limit',
            in: 'query',
            description: 'Original description',
        );

        $resolved = $this->resolver->resolveParameterWithOverride($parameter, $document);

        self::assertSame($parameter, $resolved);
    }

    #[Test]
    public function resolver_returns_same_response_if_no_ref(): void
    {
        $document = $this->createDocumentWithResponse();
        $response = new Response(
            description: 'Original description',
        );

        $resolved = $this->resolver->resolveResponseWithOverride($response, $document);

        self::assertSame($response, $resolved);
    }

    #[Test]
    public function resolver_uses_original_values_when_no_override(): void
    {
        $document = $this->createDocumentWithSchema();
        $schema = new Schema(
            ref: '#/components/schemas/User',
        );

        $resolved = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNull($resolved->ref);
        self::assertSame('Original title', $resolved->title);
        self::assertSame('Original description', $resolved->description);
    }

    #[Test]
    public function schema_json_serialize_includes_ref_summary_and_description(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'Override summary',
            refDescription: 'Override description',
        );

        $serialized = $schema->jsonSerialize();

        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('#/components/schemas/User', $serialized['$ref']);
        self::assertSame('Override summary', $serialized['summary']);
        self::assertSame('Override description', $serialized['description']);
    }

    #[Test]
    public function schema_json_serialize_ref_description_overwrites_description(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refDescription: 'Override description',
        );

        $serialized = $schema->jsonSerialize();

        self::assertSame('Override description', $serialized['description']);
    }

    #[Test]
    public function parameter_json_serialize_includes_ref_summary_and_description(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/Limit',
            refSummary: 'Override summary',
            refDescription: 'Override description',
        );

        $serialized = $parameter->jsonSerialize();

        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
    }

    #[Test]
    public function response_json_serialize_includes_ref_summary_and_description(): void
    {
        $response = new Response(
            ref: '#/components/responses/Success',
            refSummary: 'Override summary',
            refDescription: 'Override description',
        );

        $serialized = $response->jsonSerialize();

        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
    }

    #[Test]
    public function schema_ref_serializes_only_reference_fields(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'Override summary',
            refDescription: 'Override description',
            type: 'object',
            title: 'Should not appear',
            description: 'Should not appear',
            properties: ['id' => new Schema(type: 'string')],
        );

        $serialized = $schema->jsonSerialize();

        self::assertCount(3, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayNotHasKey('type', $serialized);
        self::assertArrayNotHasKey('title', $serialized);
        self::assertArrayNotHasKey('properties', $serialized);
    }

    #[Test]
    public function schema_ref_serializes_only_ref_when_no_overrides(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            type: 'object',
        );

        $serialized = $schema->jsonSerialize();

        self::assertCount(1, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
    }

    #[Test]
    public function parameter_ref_serializes_only_reference_fields(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/Limit',
            refSummary: 'Override summary',
            refDescription: 'Override description',
            name: 'limit',
            in: 'query',
            description: 'Should not appear',
            required: true,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertCount(3, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayNotHasKey('name', $serialized);
        self::assertArrayNotHasKey('in', $serialized);
        self::assertArrayNotHasKey('required', $serialized);
    }

    #[Test]
    public function parameter_ref_serializes_only_ref_when_no_overrides(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/Limit',
            name: 'limit',
        );

        $serialized = $parameter->jsonSerialize();

        self::assertCount(1, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
    }

    #[Test]
    public function response_ref_serializes_only_reference_fields(): void
    {
        $response = new Response(
            ref: '#/components/responses/Success',
            refSummary: 'Override summary',
            refDescription: 'Override description',
            summary: 'Should not appear',
            description: 'Should not appear',
        );

        $serialized = $response->jsonSerialize();

        self::assertCount(3, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
    }

    #[Test]
    public function response_ref_serializes_only_ref_when_no_overrides(): void
    {
        $response = new Response(
            ref: '#/components/responses/Success',
            description: 'Should not appear',
        );

        $serialized = $response->jsonSerialize();

        self::assertCount(1, $serialized);
        self::assertArrayHasKey('$ref', $serialized);
    }

    private function createDocumentWithSchema(): OpenApiDocument
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"schemas":{"User":{"type":"object","title":"Original title","description":"Original description"}}},"paths":{}}';
        return $this->parser->parse($json);
    }

    private function createDocumentWithParameter(): OpenApiDocument
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"parameters":{"Limit":{"name":"limit","in":"query","description":"Original description","schema":{"type":"integer"}}}},"paths":{}}';
        return $this->parser->parse($json);
    }

    private function createDocumentWithResponse(): OpenApiDocument
    {
        $json = '{"openapi":"3.2.0","info":{"title":"Test","version":"1.0"},"components":{"responses":{"Success":{"summary":"Original summary","description":"Original description"}}},"paths":{}}';
        return $this->parser->parse($json);
    }
}
