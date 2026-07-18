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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Content;

final class RefResolverParameterResponseTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function resolve_parameter_returns_parameter_instance(): void
    {
        $parameter = new Parameter(name: 'userId', in: 'path');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['UserId' => $parameter]),
        );

        $result = $this->resolver->resolveParameter('#/components/parameters/UserId', $document);

        $this->assertSame($parameter, $result);
        $this->assertSame('userId', $result->name);
        $this->assertSame('path', $result->in);
    }

    #[Test]
    public function resolve_parameter_throws_when_resolved_is_not_parameter(): void
    {
        $schema = new Schema(type: 'string');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['User' => $schema]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Parameter but got');

        $this->resolver->resolveParameter('#/components/schemas/User', $document);
    }

    #[Test]
    public function resolve_parameter_throws_for_nonexistent_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveParameter('#/components/parameters/Missing', $document);
    }

    #[Test]
    public function resolve_response_returns_response_instance(): void
    {
        $response = new Response(description: 'Success response');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['Success' => $response]),
        );

        $result = $this->resolver->resolveResponse('#/components/responses/Success', $document);

        $this->assertSame($response, $result);
        $this->assertSame('Success response', $result->description);
    }

    #[Test]
    public function resolve_response_throws_when_resolved_is_not_response(): void
    {
        $parameter = new Parameter(name: 'id', in: 'query');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['Id' => $parameter]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Response but got');

        $this->resolver->resolveResponse('#/components/parameters/Id', $document);
    }

    #[Test]
    public function resolve_response_throws_for_nonexistent_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveResponse('#/components/responses/NotFound', $document);
    }

    #[Test]
    public function resolve_parameter_caches_result(): void
    {
        $parameter = new Parameter(name: 'limit', in: 'query');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['Limit' => $parameter]),
        );

        $first = $this->resolver->resolveParameter('#/components/parameters/Limit', $document);
        $second = $this->resolver->resolveParameter('#/components/parameters/Limit', $document);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function resolve_response_caches_result(): void
    {
        $response = new Response(description: 'OK');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['Ok' => $response]),
        );

        $first = $this->resolver->resolveResponse('#/components/responses/Ok', $document);
        $second = $this->resolver->resolveResponse('#/components/responses/Ok', $document);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function resolve_parameter_follows_chained_ref(): void
    {
        $paramFinal = new Parameter(name: 'page', in: 'query', required: true);
        $paramRef = new Parameter(ref: '#/components/parameters/PageFinal');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: [
                'PageRef' => $paramRef,
                'PageFinal' => $paramFinal,
            ]),
        );

        $result = $this->resolver->resolveParameter('#/components/parameters/PageRef', $document);

        $this->assertSame('page', $result->name);
        $this->assertTrue($result->required);
    }

    #[Test]
    public function resolve_response_follows_chained_ref(): void
    {
        $responseFinal = new Response(description: 'Created', summary: 'Resource created');
        $responseRef = new Response(ref: '#/components/responses/CreatedFinal');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: [
                'CreatedRef' => $responseRef,
                'CreatedFinal' => $responseFinal,
            ]),
        );

        $result = $this->resolver->resolveResponse('#/components/responses/CreatedRef', $document);

        $this->assertSame('Created', $result->description);
        $this->assertSame('Resource created', $result->summary);
    }

    #[Test]
    public function resolve_parameter_throws_for_non_local_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('External ref not resolved. Builtin FileExternalRefResolver allows only');

        $this->resolver->resolveParameter('https://example.com/parameters.yaml', $document);
    }

    #[Test]
    public function resolve_response_throws_for_non_local_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('External ref not resolved. Builtin FileExternalRefResolver allows only');

        $this->resolver->resolveResponse('https://example.com/responses.yaml', $document);
    }

    #[Test]
    public function resolve_parameter_with_schema(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'query',
            schema: new Schema(type: 'string'),
        );
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['Filter' => $parameter]),
        );

        $result = $this->resolver->resolveParameter('#/components/parameters/Filter', $document);

        $this->assertNotNull($result->schema);
        $this->assertSame('string', $result->schema->type);
    }

    #[Test]
    public function resolve_response_with_content(): void
    {
        $response = new Response(
            description: 'List of items',
            content: new Content(mediaTypes: []),
        );
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['ItemList' => $response]),
        );

        $result = $this->resolver->resolveResponse('#/components/responses/ItemList', $document);

        $this->assertSame('List of items', $result->description);
        $this->assertNotNull($result->content);
    }

    #[Test]
    public function resolve_parameter_throws_when_ref_points_to_response(): void
    {
        $response = new Response(description: 'OK');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['Ok' => $response]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Parameter but got');

        $this->resolver->resolveParameter('#/components/responses/Ok', $document);
    }

    #[Test]
    public function resolve_response_throws_when_ref_points_to_parameter(): void
    {
        $parameter = new Parameter(name: 'id', in: 'path');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['Id' => $parameter]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Response but got');

        $this->resolver->resolveResponse('#/components/parameters/Id', $document);
    }

    #[Test]
    public function resolve_parameter_throws_when_ref_points_to_schema(): void
    {
        $schema = new Schema(type: 'object');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['Obj' => $schema]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Parameter but got');

        $this->resolver->resolveParameter('#/components/schemas/Obj', $document);
    }

    #[Test]
    public function resolve_response_throws_when_ref_points_to_schema(): void
    {
        $schema = new Schema(type: 'object');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['Obj' => $schema]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Response but got');

        $this->resolver->resolveResponse('#/components/schemas/Obj', $document);
    }

    public static function validParameterProvider(): array
    {
        return [
            'query parameter' => [
                'name' => 'limit',
                'in' => 'query',
                'ref' => 'LimitParam',
            ],
            'path parameter' => [
                'name' => 'userId',
                'in' => 'path',
                'ref' => 'UserIdParam',
            ],
            'header parameter' => [
                'name' => 'X-Request-Id',
                'in' => 'header',
                'ref' => 'RequestIdHeader',
            ],
            'cookie parameter' => [
                'name' => 'session',
                'in' => 'cookie',
                'ref' => 'SessionCookie',
            ],
        ];
    }

    #[DataProvider('validParameterProvider')]
    #[Test]
    public function resolve_various_parameter_types(string $name, string $in, string $ref): void
    {
        $parameter = new Parameter(name: $name, in: $in);
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: [$ref => $parameter]),
        );

        $result = $this->resolver->resolveParameter(
            '#/components/parameters/' . $ref,
            $document,
        );

        $this->assertSame($name, $result->name);
        $this->assertSame($in, $result->in);
    }

    public static function validResponseProvider(): array
    {
        return [
            'success response' => [
                'description' => 'Successful operation',
                'ref' => 'SuccessResponse',
            ],
            'not found response' => [
                'description' => 'Resource not found',
                'ref' => 'NotFoundResponse',
            ],
            'error response' => [
                'description' => 'Internal server error',
                'ref' => 'ErrorResponse',
            ],
            'no content response' => [
                'description' => 'No content',
                'ref' => 'NoContentResponse',
            ],
        ];
    }

    #[DataProvider('validResponseProvider')]
    #[Test]
    public function resolve_various_response_types(string $description, string $ref): void
    {
        $response = new Response(description: $description);
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: [$ref => $response]),
        );

        $result = $this->resolver->resolveResponse(
            '#/components/responses/' . $ref,
            $document,
        );

        $this->assertSame($description, $result->description);
    }

    #[Test]
    public function resolve_parameter_from_components_without_parameters_throws(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveParameter('#/components/parameters/Any', $document);
    }

    #[Test]
    public function resolve_response_from_components_without_responses_throws(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveResponse('#/components/responses/Any', $document);
    }

    #[Test]
    public function resolve_parameter_from_document_without_components_throws(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveParameter('#/components/parameters/Any', $document);
    }

    #[Test]
    public function resolve_response_from_document_without_components_throws(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveResponse('#/components/responses/Any', $document);
    }
}
