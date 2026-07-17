<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OpenApiValidatorInterfaceGetDocumentTest extends TestCase
{
    private const string MINIMAL_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: GetDocument Test
  version: 1.0.0
paths: {}
YAML;

    #[Test]
    public function interface_declares_get_document(): void
    {
        $reflection = new ReflectionClass(OpenApiValidatorInterface::class);

        self::assertTrue($reflection->hasMethod('getDocument'));
    }

    #[Test]
    public function get_document_has_return_type_open_api_document(): void
    {
        $reflection = new ReflectionClass(OpenApiValidatorInterface::class);
        $returnType = $reflection->getMethod('getDocument')->getReturnType();

        self::assertNotNull($returnType);
        self::assertSame(OpenApiDocument::class, (string) $returnType);
    }

    #[Test]
    public function validator_returns_same_document_instance(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->build();

        $documentA = $validator->getDocument();
        $documentB = $validator->getDocument();

        self::assertInstanceOf(OpenApiDocument::class, $documentA);
        self::assertSame($documentA, $documentB);
    }

    #[Test]
    public function get_document_available_via_interface_type_hint(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->build();

        self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);

        $document = $validator->getDocument();

        self::assertSame('3.2.0', $document->openapi);
        self::assertSame('GetDocument Test', $document->info->title);
    }
}
