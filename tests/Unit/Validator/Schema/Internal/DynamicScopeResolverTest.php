<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\Internal\DynamicScopeResolver;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

final class DynamicScopeResolverTest extends TestCase
{
    #[Test]
    public function enter_with_null_context_returns_frame_without_scope_frames(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Target');
        $resolver = new DynamicScopeResolver(new RefResolver(), $this->createDocument());

        $frame = $resolver->enter($schema, null);

        self::assertSame('https://example.com/target', $frame->schema->id);
        self::assertSame([], $frame->chainResources);
        self::assertFalse($frame->entersWrapperScope);

        $resolver->leave($frame, null);
    }

    #[Test]
    public function enter_pushes_wrapper_scope_frame_and_leave_pops_it(): void
    {
        $schema = new Schema(id: 'https://example.com/wrapper');
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $resolver = new DynamicScopeResolver(new RefResolver(), $this->createDocument());

        $frame = $resolver->enter($schema, $context);

        self::assertTrue($frame->entersWrapperScope);
        self::assertSame(['https://example.com/wrapper'], $this->scopeIds($context));

        $resolver->leave($frame, $context);

        self::assertSame([], $this->scopeIds($context));
    }

    #[Test]
    public function enter_with_unresolvable_ref_leaves_dynamic_scope_balanced(): void
    {
        $schema = new Schema(id: 'https://example.com/wrapper', ref: '#/components/schemas/Missing');
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $resolver = new DynamicScopeResolver(new RefResolver(), $this->createDocument());

        try {
            $resolver->enter($schema, $context);

            self::fail(UnresolvableRefException::class . ' expected');
        } catch (UnresolvableRefException) {
            self::assertSame([], $this->scopeIds($context));
        }
    }

    private function scopeIds(ValidationContext $context): array
    {
        return array_map(
            static fn(Schema $resource): ?string => $resource->id,
            $context->dynamicScope(),
        );
    }

    private function createDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('DynamicScopeResolver', '1.0.0'),
            components: new Components(schemas: [
                'Target' => new Schema(id: 'https://example.com/target'),
            ]),
        );
    }
}
