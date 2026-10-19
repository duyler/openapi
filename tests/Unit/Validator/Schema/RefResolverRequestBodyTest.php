<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefResolverRequestBodyTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function resolve_request_body_returns_request_body_instance(): void
    {
        $requestBody = new RequestBody(description: 'The user to create', required: true);
        $document = $this->documentWith($requestBody);

        $result = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);

        $this->assertSame($requestBody, $result);
        $this->assertSame('The user to create', $result->description);
        $this->assertTrue($result->required);
    }

    #[Test]
    public function resolve_request_body_preserves_content(): void
    {
        $requestBody = new RequestBody(
            content: new Content(mediaTypes: [
                'application/json' => new MediaType(schema: new Schema(type: 'object')),
            ]),
            required: true,
        );
        $document = $this->documentWith($requestBody);

        $result = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);

        $this->assertNotNull($result->content);
        $this->assertArrayHasKey('application/json', $result->content->mediaTypes);
    }

    #[Test]
    public function resolve_request_body_caches_result(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $first = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);
        $second = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function resolve_request_body_follows_chained_ref(): void
    {
        $document = $this->documentWith(
            new RequestBody(ref: '#/components/requestBodies/Body1'),
            new RequestBody(description: 'Final', required: true),
        );

        $result = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);

        $this->assertNull($result->ref);
        $this->assertSame('Final', $result->description);
        $this->assertTrue($result->required);
    }

    #[Test]
    public function resolve_request_body_throws_for_nonexistent_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveRequestBody('#/components/requestBodies/Missing', $document);
    }

    #[Test]
    public function resolve_request_body_throws_for_document_without_components(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveRequestBody('#/components/requestBodies/Any', $document);
    }

    #[Test]
    public function resolve_request_body_throws_when_ref_points_to_schema(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['User' => new Schema(type: 'object')]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected RequestBody but got');

        $this->resolver->resolveRequestBody('#/components/schemas/User', $document);
    }

    #[Test]
    public function resolve_request_body_throws_when_ref_points_to_response(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['Ok' => new Response(description: 'OK')]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected RequestBody but got');

        $this->resolver->resolveRequestBody('#/components/responses/Ok', $document);
    }

    #[Test]
    public function resolve_response_throws_when_ref_points_to_request_body(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Response but got');

        $this->resolver->resolveResponse('#/components/requestBodies/Body0', $document);
    }

    #[Test]
    public function resolve_parameter_throws_when_ref_points_to_request_body(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Parameter but got');

        $this->resolver->resolveParameter('#/components/requestBodies/Body0', $document);
    }

    #[Test]
    public function resolve_schema_throws_when_ref_points_to_request_body(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Schema but got');

        $this->resolver->resolve('#/components/requestBodies/Body0', $document);
    }

    #[Test]
    public function resolve_request_body_throws_for_non_local_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('External ref not resolved. Builtin FileExternalRefResolver allows only');

        $this->resolver->resolveRequestBody('https://example.com/bodies.yaml', $document);
    }

    #[Test]
    public function resolve_request_body_throws_on_circular_ref(): void
    {
        $document = $this->documentWith(
            new RequestBody(ref: '#/components/requestBodies/Body1'),
            new RequestBody(ref: '#/components/requestBodies/Body0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);
    }

    #[Test]
    public function resolve_with_override_returns_same_instance_when_not_a_reference(): void
    {
        $requestBody = new RequestBody(description: 'Inline', required: true);
        $document = $this->documentWith(new RequestBody(required: false));

        $result = $this->resolver->resolveRequestBodyWithOverride($requestBody, $document);

        $this->assertSame($requestBody, $result);
    }

    #[Test]
    public function resolve_with_override_replaces_description_and_keeps_content_and_required(): void
    {
        $target = new RequestBody(
            description: 'Declared in components',
            content: new Content(mediaTypes: ['application/json' => new MediaType()]),
            required: true,
        );
        $document = $this->documentWith($target);

        $result = $this->resolver->resolveRequestBodyWithOverride(
            new RequestBody(
                ref: '#/components/requestBodies/Body0',
                refDescription: 'Overridden at the call site',
            ),
            $document,
        );

        $this->assertNull($result->ref);
        $this->assertSame('Overridden at the call site', $result->description);
        $this->assertSame($target->content, $result->content);
        $this->assertTrue($result->required);
    }

    #[Test]
    public function resolve_with_override_falls_back_to_the_referenced_description(): void
    {
        $document = $this->documentWith(new RequestBody(description: 'Declared in components', required: true));

        $result = $this->resolver->resolveRequestBodyWithOverride(
            new RequestBody(ref: '#/components/requestBodies/Body0'),
            $document,
        );

        $this->assertSame('Declared in components', $result->description);
    }

    #[Test]
    public function resolve_with_override_drops_the_reference_sibling_summary(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $result = $this->resolver->resolveRequestBodyWithOverride(
            new RequestBody(
                ref: '#/components/requestBodies/Body0',
                refSummary: 'Call-site summary',
            ),
            $document,
        );

        $this->assertNull($result->refSummary);
        $this->assertNull($result->refDescription);
    }

    #[Test]
    public function clear_discards_the_request_body_cache(): void
    {
        $document = $this->documentWith(new RequestBody(required: true));

        $first = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);
        $this->resolver->clear();
        $second = $this->resolver->resolveRequestBody('#/components/requestBodies/Body0', $document);

        $this->assertSame($first, $second);
    }

    private function documentWith(RequestBody ...$bodies): OpenApiDocument
    {
        $named = [];

        foreach ($bodies as $index => $body) {
            $named['Body' . $index] = $body;
        }

        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(requestBodies: $named),
        );
    }
}
