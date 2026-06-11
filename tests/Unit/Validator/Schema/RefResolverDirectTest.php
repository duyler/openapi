<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Parameter;

/** @internal */
final class RefResolverDirectTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function resolve_returns_schema_from_components(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => new Schema(
                        type: 'object',
                        properties: ['name' => new Schema(type: 'string')],
                    ),
                ],
            ),
        );

        $result = $this->resolver->resolve('#/components/schemas/User', $document);

        self::assertInstanceOf(Schema::class, $result);
        self::assertSame('object', $result->type);
    }

    #[Test]
    public function resolve_throws_for_non_schema_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                parameters: [
                    'userId' => new Parameter(
                        name: 'userId',
                        in: 'path',
                    ),
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Expected Schema');

        $this->resolver->resolve('#/components/parameters/userId', $document);
    }

    #[Test]
    public function resolve_throws_for_missing_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolve('#/components/schemas/Missing', $document);
    }

    #[Test]
    public function resolve_throws_for_external_ref(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );

        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolve('https://example.com/schema.json', $document);
    }

    #[Test]
    public function resolve_caches_result(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Cached' => new Schema(type: 'string'),
                ],
            ),
        );

        $first = $this->resolver->resolve('#/components/schemas/Cached', $document);
        $second = $this->resolver->resolve('#/components/schemas/Cached', $document);

        self::assertSame($first, $second);
    }

    #[Test]
    public function resolve_follows_nested_refs(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Wrapper' => new Schema(
                        ref: '#/components/schemas/Inner',
                    ),
                    'Inner' => new Schema(type: 'integer'),
                ],
            ),
        );

        $result = $this->resolver->resolve('#/components/schemas/Wrapper', $document);

        self::assertSame('integer', $result->type);
    }
}
