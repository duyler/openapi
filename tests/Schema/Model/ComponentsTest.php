<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SecurityScheme;

final class ComponentsTest extends TestCase
{
    #[Test]
    public function can_create_components(): void
    {
        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        self::assertInstanceOf(Components::class, $components);
    }

    #[Test]
    public function can_create_components_with_schemas(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        self::assertNotNull($components->schemas);
        self::assertArrayHasKey('User', $components->schemas);
        self::assertInstanceOf(Schema::class, $components->schemas['User']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayNotHasKey('schemas', $serialized);
        self::assertArrayNotHasKey('responses', $serialized);
    }

    #[Test]
    public function json_serialize_includes_schemas(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('schemas', $serialized);
        self::assertSame(['User' => $schema], $serialized['schemas']);
    }

    #[Test]
    public function json_serialize_includes_parameters_when_not_null(): void
    {
        $parameter = new Parameter(
            name: 'userId',
            in: 'path',
            required: true,
            schema: new Schema(type: 'string'),
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: ['userId' => $parameter],
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('parameters', $serialized);
        self::assertArrayNotHasKey('schemas', $serialized);
        self::assertSame(['userId' => $parameter], $serialized['parameters']);
    }

    #[Test]
    public function json_serialize_includes_examples_when_not_null(): void
    {
        $example = new Example(
            summary: 'Test example',
            value: ['test' => 'data'],
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: ['testExample' => $example],
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('examples', $serialized);
        self::assertArrayNotHasKey('parameters', $serialized);
        self::assertSame(['testExample' => $example], $serialized['examples']);
    }

    #[Test]
    public function json_serialize_includes_request_bodies_when_not_null(): void
    {
        $content = new Content(
            mediaTypes: ['application/json' => new MediaType(
                schema: new Schema(type: 'object'),
            )],
        );
        $requestBody = new RequestBody(
            description: 'Test body',
            content: $content,
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: ['TestBody' => $requestBody],
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('requestBodies', $serialized);
        self::assertArrayNotHasKey('examples', $serialized);
        self::assertSame(['TestBody' => $requestBody], $serialized['requestBodies']);
    }

    #[Test]
    public function json_serialize_includes_headers_when_not_null(): void
    {
        $header = new Header(
            description: 'Test header',
            schema: new Schema(type: 'string'),
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: ['X-Test-Header' => $header],
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('headers', $serialized);
        self::assertArrayNotHasKey('requestBodies', $serialized);
        self::assertSame(['X-Test-Header' => $header], $serialized['headers']);
    }

    #[Test]
    public function json_serialize_includes_security_schemes_when_not_null(): void
    {
        $securityScheme = new SecurityScheme(
            type: 'http',
            scheme: 'bearer',
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: ['bearerAuth' => $securityScheme],
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('securitySchemes', $serialized);
        self::assertArrayNotHasKey('headers', $serialized);
        self::assertSame(['bearerAuth' => $securityScheme], $serialized['securitySchemes']);
    }

    #[Test]
    public function json_serialize_includes_all_fields_when_not_null(): void
    {
        $schema = new Schema(type: 'object');
        $parameter = new Parameter(
            name: 'test',
            in: 'query',
            required: true,
            schema: new Schema(type: 'string'),
        );
        $example = new Example(summary: 'Test', value: ['data' => 'test']);
        $content = new Content(
            mediaTypes: ['application/json' => new MediaType(schema: new Schema(type: 'object'))],
        );
        $requestBody = new RequestBody(
            description: 'Test',
            content: $content,
        );
        $header = new Header(
            description: 'Test',
            schema: new Schema(type: 'string'),
        );
        $securityScheme = new SecurityScheme(type: 'http', scheme: 'bearer');

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: ['test' => $parameter],
            examples: ['test' => $example],
            requestBodies: ['test' => $requestBody],
            headers: ['test' => $header],
            securitySchemes: ['test' => $securityScheme],
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('schemas', $serialized);
        self::assertArrayHasKey('parameters', $serialized);
        self::assertArrayHasKey('examples', $serialized);
        self::assertArrayHasKey('requestBodies', $serialized);
        self::assertArrayHasKey('headers', $serialized);
        self::assertArrayHasKey('securitySchemes', $serialized);
        self::assertArrayNotHasKey('responses', $serialized);
        self::assertArrayNotHasKey('links', $serialized);
    }

    #[Test]
    public function json_serialize_includes_links_when_not_null(): void
    {
        $link = new Link(
            operationRef: 'operationId',
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: ['TestLink' => $link],
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('links', $serialized);
        self::assertArrayNotHasKey('securitySchemes', $serialized);
        self::assertSame(['TestLink' => $link], $serialized['links']);
    }

    #[Test]
    public function json_serialize_includes_path_items_when_not_null(): void
    {
        $pathItem = new PathItem(
            get: null,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: ['testPath' => $pathItem],
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('pathItems', $serialized);
        self::assertArrayNotHasKey('links', $serialized);
        self::assertSame(['testPath' => $pathItem], $serialized['pathItems']);
    }

    #[Test]
    public function json_serialize_includes_callbacks_when_not_null(): void
    {
        $callbacks = new Callbacks(
            callbacks: [
                'testCallback' => [
                    '{$request.query#/url}' => new PathItem(
                        get: null,
                        post: null,
                        put: null,
                        delete: null,
                        options: null,
                        head: null,
                        patch: null,
                        trace: null,
                    ),
                ],
            ],
        );

        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: ['testCallbacks' => $callbacks],
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('callbacks', $serialized);
        self::assertArrayNotHasKey('pathItems', $serialized);
        self::assertSame(['testCallbacks' => $callbacks], $serialized['callbacks']);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(type: 'object');
        $parameter = new Parameter(
            name: 'test',
            in: 'query',
            required: true,
            schema: new Schema(type: 'string'),
        );
        $example = new Example(summary: 'Test', value: ['data' => 'test']);
        $content = new Content(
            mediaTypes: ['application/json' => new MediaType(schema: new Schema(type: 'object'))],
        );
        $requestBody = new RequestBody(
            description: 'Test',
            content: $content,
        );
        $header = new Header(
            description: 'Test',
            schema: new Schema(type: 'string'),
        );
        $securityScheme = new SecurityScheme(type: 'http', scheme: 'bearer');
        $link = new Link(operationRef: 'test');
        $callbacks = new Callbacks(callbacks: []);
        $pathItem = new PathItem();

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: ['test' => $parameter],
            examples: ['test' => $example],
            requestBodies: ['test' => $requestBody],
            headers: ['test' => $header],
            securitySchemes: ['test' => $securityScheme],
            links: ['test' => $link],
            callbacks: ['test' => $callbacks],
            pathItems: ['test' => $pathItem],
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('schemas', $serialized);
        self::assertArrayHasKey('parameters', $serialized);
        self::assertArrayHasKey('examples', $serialized);
        self::assertArrayHasKey('requestBodies', $serialized);
        self::assertArrayHasKey('headers', $serialized);
        self::assertArrayHasKey('securitySchemes', $serialized);
        self::assertArrayHasKey('links', $serialized);
        self::assertArrayHasKey('callbacks', $serialized);
        self::assertArrayHasKey('pathItems', $serialized);
        self::assertArrayNotHasKey('responses', $serialized);
    }

    #[Test]
    public function json_serialize_includes_responses_when_not_null(): void
    {
        $response = new Response(
            description: 'Test response',
        );

        $components = new Components(
            schemas: null,
            responses: ['TestResponse' => $response],
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('responses', $serialized);
        self::assertArrayNotHasKey('schemas', $serialized);
        self::assertSame(['TestResponse' => $response], $serialized['responses']);
    }

    #[Test]
    public function components_has_media_types(): void
    {
        $components = new Components(
            mediaTypes: [
                'ProblemJson' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ],
        );

        self::assertNotNull($components->mediaTypes);
        self::assertArrayHasKey('ProblemJson', $components->mediaTypes);
    }

    #[Test]
    public function json_serialize_includes_media_types(): void
    {
        $mediaType = new MediaType(
            schema: new Schema(type: 'object'),
        );

        $components = new Components(
            mediaTypes: ['ProblemJson' => $mediaType],
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('mediaTypes', $serialized);
        self::assertSame(['ProblemJson' => $mediaType], $serialized['mediaTypes']);
    }

    #[Test]
    public function json_serialize_includes_media_types_with_all_fields(): void
    {
        $schema = new Schema(type: 'object');
        $mediaType = new MediaType(
            schema: $schema,
            example: new Example(value: ['type' => 'about:blank']),
        );

        $components = new Components(
            schemas: ['Problem' => $schema],
            mediaTypes: ['ProblemJson' => $mediaType],
        );

        $serialized = $components->jsonSerialize();

        self::assertArrayHasKey('schemas', $serialized);
        self::assertArrayHasKey('mediaTypes', $serialized);
    }
}
