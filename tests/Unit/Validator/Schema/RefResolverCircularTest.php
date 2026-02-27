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

final class RefResolverCircularTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function direct_circular_ref_throws_exception(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA, 'B' => $schemaB]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->resolver->resolve('#/components/schemas/A', $document);
    }

    #[Test]
    public function indirect_circular_ref_throws_exception(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/C');
        $schemaC = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA, 'B' => $schemaB, 'C' => $schemaC]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->resolver->resolve('#/components/schemas/A', $document);
    }

    #[Test]
    public function self_referencing_schema_throws_exception(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->resolver->resolve('#/components/schemas/A', $document);
    }

    #[Test]
    public function valid_ref_chain_resolves(): void
    {
        $schemaC = new Schema(title: 'FinalSchema', type: 'object');
        $schemaB = new Schema(ref: '#/components/schemas/C');
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA, 'B' => $schemaB, 'C' => $schemaC]),
        );

        $result = $this->resolver->resolve('#/components/schemas/A', $document);

        self::assertSame('FinalSchema', $result->title);
        self::assertSame('object', $result->type);
    }

    #[Test]
    public function error_message_contains_full_path(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/C');
        $schemaC = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA, 'B' => $schemaB, 'C' => $schemaC]),
        );

        try {
            $this->resolver->resolve('#/components/schemas/A', $document);
            $this->fail('Expected UnresolvableRefException was not thrown');
        } catch (UnresolvableRefException $e) {
            self::assertStringContainsString('#/components/schemas/A', $e->reason);
            self::assertStringContainsString('#/components/schemas/B', $e->reason);
            self::assertStringContainsString('#/components/schemas/C', $e->reason);
            self::assertStringContainsString(' -> ', $e->reason);
        }
    }

    #[Test]
    public function parameter_circular_ref_throws_exception(): void
    {
        $paramA = new Parameter(ref: '#/components/parameters/B');
        $paramB = new Parameter(ref: '#/components/parameters/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['A' => $paramA, 'B' => $paramB]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->resolver->resolveParameter('#/components/parameters/A', $document);
    }

    #[Test]
    public function response_circular_ref_throws_exception(): void
    {
        $responseA = new Response(ref: '#/components/responses/B');
        $responseB = new Response(ref: '#/components/responses/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['A' => $responseA, 'B' => $responseB]),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->resolver->resolveResponse('#/components/responses/A', $document);
    }

    #[Test]
    public function schema_without_ref_resolves_directly(): void
    {
        $schema = new Schema(title: 'DirectSchema', type: 'string');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['Direct' => $schema]),
        );

        $result = $this->resolver->resolve('#/components/schemas/Direct', $document);

        self::assertSame('DirectSchema', $result->title);
        self::assertSame('string', $result->type);
    }

    #[Test]
    public function valid_parameter_ref_chain_resolves(): void
    {
        $paramB = new Parameter(name: 'id', in: 'path');
        $paramA = new Parameter(ref: '#/components/parameters/B');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(parameters: ['A' => $paramA, 'B' => $paramB]),
        );

        $result = $this->resolver->resolveParameter('#/components/parameters/A', $document);

        self::assertSame('id', $result->name);
        self::assertSame('path', $result->in);
    }

    #[Test]
    public function valid_response_ref_chain_resolves(): void
    {
        $responseB = new Response(description: 'Success');
        $responseA = new Response(ref: '#/components/responses/B');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(responses: ['A' => $responseA, 'B' => $responseB]),
        );

        $result = $this->resolver->resolveResponse('#/components/responses/A', $document);

        self::assertSame('Success', $result->description);
    }

    #[Test]
    public function deep_valid_ref_chain_resolves(): void
    {
        $schemaE = new Schema(title: 'FinalSchema');
        $schemaD = new Schema(ref: '#/components/schemas/E');
        $schemaC = new Schema(ref: '#/components/schemas/D');
        $schemaB = new Schema(ref: '#/components/schemas/C');
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: [
                'A' => $schemaA,
                'B' => $schemaB,
                'C' => $schemaC,
                'D' => $schemaD,
                'E' => $schemaE,
            ]),
        );

        $result = $this->resolver->resolve('#/components/schemas/A', $document);

        self::assertSame('FinalSchema', $result->title);
    }
}
