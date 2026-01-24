<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RefResolverTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function resolve_local_ref_to_schema(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve('#/components/schemas/User', $document);

        $this->assertSame($userSchema, $resolved);
        $this->assertSame('User', $resolved->title);
    }

    #[Test]
    public function resolve_nested_schema(): void
    {
        $addressSchema = new Schema(title: 'Address');
        $userSchema = new Schema(
            title: 'User',
            properties: [
                'address' => $addressSchema,
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                    'Address' => $addressSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve('#/components/schemas/Address', $document);

        $this->assertSame($addressSchema, $resolved);
        $this->assertSame('Address', $resolved->title);
    }

    #[Test]
    public function throw_error_for_invalid_ref(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/invalid/path": Property does not exist');

        $this->resolver->resolve('#/invalid/path', $document);
    }

    #[Test]
    public function throw_error_for_missing_component(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/components/schemas/Missing": Value is null');

        $this->resolver->resolve('#/components/schemas/Missing', $document);
    }

    #[Test]
    public function throw_error_for_non_local_ref(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "http://example.com/schema": Only local refs');

        $this->resolver->resolve('http://example.com/schema', $document);
    }

    #[Test]
    public function cache_resolved_refs(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $first = $this->resolver->resolve('#/components/schemas/User', $document);
        $second = $this->resolver->resolve('#/components/schemas/User', $document);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function throw_error_for_ref_to_non_object(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/openapi": Value is not an object or array');

        $this->resolver->resolve('#/openapi', $document);
    }

    #[Test]
    public function resolve_ref_to_nested_property(): void
    {
        $addressSchema = new Schema(title: 'Address');
        $userSchema = new Schema(
            title: 'User',
            properties: [
                'address' => $addressSchema,
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve('#/components/schemas/User/properties/address', $document);

        $this->assertSame($addressSchema, $resolved);
        $this->assertSame('Address', $resolved->title);
    }

    #[Test]
    public function throw_error_for_nonexistent_property_in_path(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/components/schemas/User/nonexistent": Property does not exist');

        $this->resolver->resolve('#/components/schemas/User/nonexistent', $document);
    }

    #[Test]
    public function throw_error_for_null_value_in_path(): void
    {
        $userSchema = new Schema(
            title: 'User',
            properties: [
                'address' => null,
            ],
        );
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/components/schemas/User/properties/address": Value is null');

        $this->resolver->resolve('#/components/schemas/User/properties/address', $document);
    }

    #[Test]
    public function throw_error_for_ref_to_string_value(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/info/title": Value is not an object or array');

        $this->resolver->resolve('#/info/title', $document);
    }

    #[Test]
    public function cache_is_document_specific(): void
    {
        $userSchema = new Schema(title: 'User');

        $document1 = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $document2 = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Another API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $resolvedFromDoc1 = $this->resolver->resolve('#/components/schemas/User', $document1);
        $resolvedFromDoc2 = $this->resolver->resolve('#/components/schemas/User', $document2);

        $this->assertSame($resolvedFromDoc1, $resolvedFromDoc2);
        $this->assertSame($userSchema, $resolvedFromDoc1);
    }

    #[Test]
    public function throw_error_for_ref_to_non_schema_object(): void
    {
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/components": Value is null');

        $this->resolver->resolve('#/components', $document);
    }

    #[Test]
    public function throw_error_for_ref_to_property_array(): void
    {
        $userSchema = new Schema(
            title: 'User',
            properties: [
                'tags' => ['tag1', 'tag2'],
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Cannot resolve $ref "#/components/schemas/User/properties/tags/0": Value is not an object or array');

        $this->resolver->resolve('#/components/schemas/User/properties/tags/0', $document);
    }
}
