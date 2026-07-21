<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * P-030 regression: UnresolvableRefException::getMessage() must NOT leak
 * the internal reference traversal path. The path is preserved in a
 * separate internalTrace property for logging only. A PSR-15 middleware
 * that surfaces getMessage() to the client must not disclose internal
 * ref names, file paths, or URLs.
 *
 * @internal
 */
final class RefResolverInfoLeakTest extends TestCase
{
    private RefResolver $resolver;

    #[Override]
    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function circular_ref_exception_does_not_leak_path(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(title: 'Test', version: '1.0'),
            components: new Components(schemas: ['A' => $schemaA, 'B' => $schemaB]),
        );

        try {
            $this->resolver->resolve('#/components/schemas/A', $document);
            self::fail('Expected UnresolvableRefException was not thrown');
        } catch (UnresolvableRefException $e) {
            self::assertStringNotContainsString(
                '#/components/schemas/B',
                $e->getMessage(),
                'getMessage() must not leak the path of the cycle (P-030)',
            );
            self::assertStringNotContainsString(
                ' -> ',
                $e->getMessage(),
                'getMessage() must not leak the cycle path arrow notation (P-030)',
            );

            self::assertNotNull($e->internalTrace(reveal: true));
            self::assertStringContainsString(
                '#/components/schemas/A',
                $e->internalTrace(reveal: true),
                'internalTrace must preserve the cycle path for logging',
            );
            self::assertStringContainsString(
                '#/components/schemas/B',
                $e->internalTrace(reveal: true),
                'internalTrace must preserve the cycle path for logging',
            );
        }
    }

    #[Test]
    public function circular_ref_three_node_cycle_does_not_leak_intermediate_refs(): void
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
            self::fail('Expected UnresolvableRefException was not thrown');
        } catch (UnresolvableRefException $e) {
            $message = $e->getMessage();

            self::assertStringNotContainsString('#/components/schemas/B', $message);
            self::assertStringNotContainsString('#/components/schemas/C', $message);
            self::assertStringContainsString('Circular reference detected', $message);
        }
    }
}
