<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RefResolverOverrideTest extends TestCase
{
    private RefResolver $resolver;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();

        $this->document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => new Schema(
                        type: 'object',
                        description: 'Original description',
                        title: 'Original Title',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ],
                parameters: [
                    'userId' => new Parameter(
                        name: 'userId',
                        in: 'path',
                        required: true,
                        schema: new Schema(type: 'string'),
                    ),
                ],
                responses: [
                    'success' => new Response(
                        description: 'Success response',
                    ),
                ],
            ),
        );
    }

    #[Test]
    public function resolve_parameter_returns_parameter_object(): void
    {
        $result = $this->resolver->resolveParameter('#/components/parameters/userId', $this->document);

        self::assertInstanceOf(Parameter::class, $result);
        self::assertSame('userId', $result->name);
        self::assertSame('path', $result->in);
    }

    #[Test]
    public function resolve_parameter_throws_for_non_parameter_ref(): void
    {
        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveParameter('#/components/schemas/User', $this->document);
    }

    #[Test]
    public function resolve_response_returns_response_object(): void
    {
        $result = $this->resolver->resolveResponse('#/components/responses/success', $this->document);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame('Success response', $result->description);
    }

    #[Test]
    public function resolve_response_throws_for_non_response_ref(): void
    {
        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveResponse('#/components/schemas/User', $this->document);
    }

    #[Test]
    public function resolve_schema_with_override_returns_schema_as_is_when_no_ref(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame($schema, $result);
    }

    #[Test]
    public function resolve_schema_with_override_resolves_ref(): void
    {
        $schema = new Schema(ref: '#/components/schemas/User');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertNull($result->ref);
        self::assertSame('object', $result->type);
    }

    #[Test]
    public function resolve_schema_with_override_applies_ref_description(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refDescription: 'Custom description',
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Custom description', $result->description);
    }

    #[Test]
    public function resolve_schema_with_override_uses_resolved_description_when_no_override(): void
    {
        $schema = new Schema(ref: '#/components/schemas/User');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Original description', $result->description);
    }

    #[Test]
    public function resolve_parameter_with_override_returns_parameter_as_is_when_no_ref(): void
    {
        $parameter = new Parameter(name: 'test', in: 'query');

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertSame($parameter, $result);
    }

    #[Test]
    public function resolve_parameter_with_override_resolves_ref(): void
    {
        $parameter = new Parameter(ref: '#/components/parameters/userId');

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertNull($result->ref);
        self::assertSame('userId', $result->name);
    }

    #[Test]
    public function resolve_parameter_with_override_applies_ref_description(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/userId',
            refDescription: 'Custom param description',
        );

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertSame('Custom param description', $result->description);
    }

    #[Test]
    public function resolve_response_with_override_returns_response_as_is_when_no_ref(): void
    {
        $response = new Response(description: 'Local response');

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame($response, $result);
    }

    #[Test]
    public function resolve_response_with_override_resolves_ref(): void
    {
        $response = new Response(ref: '#/components/responses/success');

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertNull($result->ref);
        self::assertSame('Success response', $result->description);
    }

    #[Test]
    public function resolve_response_with_override_applies_ref_summary(): void
    {
        $response = new Response(
            ref: '#/components/responses/success',
            refSummary: 'Custom summary',
        );

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame('Custom summary', $result->summary);
    }

    #[Test]
    public function resolve_response_with_override_applies_ref_description(): void
    {
        $response = new Response(
            ref: '#/components/responses/success',
            refDescription: 'Custom response description',
        );

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame('Custom response description', $result->description);
    }

    #[Test]
    public function resolve_schema_with_override_applies_ref_summary_as_title(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'Override Title',
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Override Title', $result->title);
    }
}
