<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RefValidationTest extends TestCase
{
    #[Test]
    public function compile_resolves_simple_ref(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'street' => new Schema(type: 'string'),
            ],
        );

        $components = new Components(
            schemas: [
                'Address' => $addressSchema,
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
            components: $components,
        );

        $userSchema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    ref: '#/components/schemas/Address',
                ),
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compileWithRefResolution($userSchema, 'UserValidator', $document);

        self::assertStringContainsString("['address']", $code);
        self::assertStringContainsString("['street']", $code);
    }

    #[Test]
    public function compile_resolves_nested_refs(): void
    {
        $innerSchema = new Schema(type: 'string');
        $outerSchema = new Schema(type: 'object', properties: ['inner' => $innerSchema]);

        $components = new Components(
            schemas: [
                'Inner' => $innerSchema,
                'Outer' => $outerSchema,
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
            components: $components,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'outer' => new Schema(ref: '#/components/schemas/Outer'),
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compileWithRefResolution($schema, 'NestedRefValidator', $document);

        self::assertStringContainsString("['outer']", $code);
        self::assertStringContainsString("['inner']", $code);
    }

    #[Test]
    public function compile_throws_on_missing_ref(): void
    {
        $components = new Components(schemas: []);
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
            components: $components,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'missing' => new Schema(ref: '#/components/schemas/Missing'),
            ],
        );

        $compiler = new ValidatorCompiler();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema not found: Missing');

        $compiler->compileWithRefResolution($schema, 'MissingRefValidator', $document);
    }

    #[Test]
    public function compile_throws_on_unsupported_ref_format(): void
    {
        $components = new Components(schemas: []);
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
            components: $components,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'external' => new Schema(ref: 'http://example.com/schema.json'),
            ],
        );

        $compiler = new ValidatorCompiler();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported $ref');

        $compiler->compileWithRefResolution($schema, 'ExternalRefValidator', $document);
    }

    #[Test]
    public function compile_handles_circular_refs_gracefully(): void
    {
        $circularSchema = new Schema(
            type: 'object',
            properties: [
                'parent' => new Schema(ref: '#/components/schemas/Circular'),
            ],
        );

        $components = new Components(
            schemas: [
                'Circular' => $circularSchema,
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
            components: $components,
        );

        $schema = new Schema(ref: '#/components/schemas/Circular');

        $compiler = new ValidatorCompiler();

        $this->expectException(Throwable::class);
        $compiler->compileWithRefResolution($schema, 'CircularRefValidator', $document);
    }
}
