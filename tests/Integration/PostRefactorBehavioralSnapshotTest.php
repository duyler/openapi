<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaSiblingMerger;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostRefactorBehavioralSnapshotTest extends TestCase
{
    #[Test]
    public function sibling_merger_nullable_widening_semantics_sibling_false_keeps_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: false);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertTrue($merged->nullable);
    }

    #[Test]
    public function sibling_merger_nullable_widening_semantics_both_true_allows_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: true);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertTrue($merged->nullable);
    }

    #[Test]
    public function sibling_merger_type_conflict_clears_type_and_wraps_in_all_of(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(type: 'integer');

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertNull($merged->type);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame('string', $merged->allOf[0]->type);
        self::assertSame('integer', $merged->allOf[1]->type);
    }

    #[Test]
    public function sibling_merger_format_conflict_clears_format_and_wraps_in_all_of(): void
    {
        $resolved = new Schema(type: 'string', format: 'email');
        $sibling = new Schema(format: 'uri');

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertNull($merged->format);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame('email', $merged->allOf[0]->format);
        self::assertSame('uri', $merged->allOf[1]->format);
    }

    #[Test]
    public function sibling_merger_compatible_type_arrays_intersect_to_single_value(): void
    {
        $resolved = new Schema(type: ['string', 'null']);
        $sibling = new Schema(type: ['string']);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertSame('string', $merged->type);
        self::assertNull($merged->allOf);
    }

    #[Test]
    public function sibling_merger_items_recursively_merges_numeric_bounds(): void
    {
        $resolved = new Schema(
            type: 'array',
            items: new Schema(type: 'integer', minimum: 0.0),
        );
        $sibling = new Schema(items: new Schema(maximum: 100.0));

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertInstanceOf(Schema::class, $merged->items);
        self::assertSame('integer', $merged->items->type);
        self::assertSame(0.0, $merged->items->minimum);
        self::assertSame(100.0, $merged->items->maximum);
    }

    #[Test]
    public function sibling_merger_any_of_on_both_sides_wraps_in_all_of_not_concatenates(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolvedB = new Schema(type: 'integer');
        $siblingC = new Schema(type: 'boolean');
        $siblingD = new Schema(type: 'null');

        $resolved = new Schema(anyOf: [$resolvedA, $resolvedB]);
        $sibling = new Schema(anyOf: [$siblingC, $siblingD]);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertNull($merged->anyOf);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame([$resolvedA, $resolvedB], $merged->allOf[0]->anyOf);
        self::assertSame([$siblingC, $siblingD], $merged->allOf[1]->anyOf);
    }

    #[Test]
    public function sibling_merger_numeric_bounds_take_stricter_of_both(): void
    {
        $resolved = new Schema(type: 'integer', minimum: 0.0, maximum: 100.0);
        $sibling = new Schema(minimum: -10.0, maximum: 50.0);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertSame(0.0, $merged->minimum);
        self::assertSame(50.0, $merged->maximum);
    }

    #[Test]
    public function sibling_merger_required_unions_both_lists_dedup(): void
    {
        $resolved = new Schema(type: 'object', required: ['id', 'name']);
        $sibling = new Schema(required: ['name', 'email']);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        self::assertSame(['id', 'name', 'email'], $merged->required);
    }

    #[Test]
    public function ref_resolver_resolves_simple_internal_schema_ref(): void
    {
        $pet = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );
        $document = $this->buildDocument(['Pet' => $pet]);
        $resolver = new RefResolver();

        $resolved = $resolver->resolve('#/components/schemas/Pet', $document);

        self::assertSame($pet, $resolved);
        self::assertSame('object', $resolved->type);
    }

    #[Test]
    public function ref_resolver_resolves_ref_via_another_schema_property(): void
    {
        $address = new Schema(type: 'object', properties: ['city' => new Schema(type: 'string')]);
        $user = new Schema(
            type: 'object',
            properties: ['address' => new Schema(ref: '#/components/schemas/Address')],
        );
        $document = $this->buildDocument(['User' => $user, 'Address' => $address]);
        $resolver = new RefResolver();

        $userResolved = $resolver->resolve('#/components/schemas/User', $document);

        self::assertSame('object', $userResolved->type);
        self::assertNotNull($userResolved->properties);
        self::assertArrayHasKey('address', $userResolved->properties);
        self::assertSame('#/components/schemas/Address', $userResolved->properties['address']->ref);
    }

    #[Test]
    public function ref_resolver_detects_circular_ref_and_throws(): void
    {
        $document = $this->buildDocument([
            'A' => new Schema(ref: '#/components/schemas/B'),
            'B' => new Schema(ref: '#/components/schemas/A'),
        ]);
        $resolver = new RefResolver();

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $resolver->resolve('#/components/schemas/A', $document);
    }

    #[Test]
    public function ref_resolver_resolves_external_ref_via_injected_resolver(): void
    {
        $externalSchema = new Schema(
            type: 'object',
            title: 'ExternalUser',
            properties: ['id' => new Schema(type: 'integer')],
        );
        $externalRefResolver = new readonly class ($externalSchema) implements ExternalRefResolverInterface {
            public function __construct(private Schema $schema) {}

            public function resolve(string $ref): Schema
            {
                return $this->schema;
            }
        };
        $resolver = new RefResolver($externalRefResolver);
        $document = $this->buildDocument([]);

        $resolved = $resolver->resolve('https://example.com/schemas/user.json', $document);

        self::assertSame($externalSchema, $resolved);
        self::assertSame('ExternalUser', $resolved->title);
    }

    #[Test]
    public function ref_resolver_resolves_ten_level_deep_ref_chain_to_leaf(): void
    {
        $schemas = [];
        for ($i = 1; $i <= 10; ++$i) {
            $schemas["Level{$i}"] = $i < 10
                ? new Schema(ref: "#/components/schemas/Level" . ($i + 1))
                : new Schema(type: 'string', title: 'LeafSchema');
        }
        $document = $this->buildDocument($schemas);
        $resolver = new RefResolver();

        $resolved = $resolver->resolve('#/components/schemas/Level1', $document);

        self::assertSame('string', $resolved->type);
        self::assertSame('LeafSchema', $resolved->title);
    }

    #[Test]
    public function ref_resolver_json_pointer_navigates_into_schema_property(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 2);
        $pet = new Schema(
            type: 'object',
            properties: ['name' => $nameSchema],
        );
        $document = $this->buildDocument(['Pet' => $pet]);
        $resolver = new RefResolver();

        $resolved = $resolver->resolve('#/components/schemas/Pet/properties/name', $document);

        self::assertSame($nameSchema, $resolved);
        self::assertSame('string', $resolved->type);
        self::assertSame(2, $resolved->minLength);
    }

    #[Test]
    public function ref_resolver_caches_resolved_schema_on_repeat_call(): void
    {
        $schema = new Schema(type: 'string', title: 'Cached');
        $document = $this->buildDocument(['Cached' => $schema]);
        $resolver = new RefResolver();

        $first = $resolver->resolve('#/components/schemas/Cached', $document);
        $second = $resolver->resolve('#/components/schemas/Cached', $document);

        self::assertSame($first, $second);
    }

    #[Test]
    public function ref_resolver_throws_unresolvable_for_missing_schema(): void
    {
        $document = $this->buildDocument([]);
        $resolver = new RefResolver();

        $this->expectException(UnresolvableRefException::class);

        $resolver->resolve('#/components/schemas/Missing', $document);
    }

    /**
     * @param array<string, Schema> $schemas
     */
    private function buildDocument(array $schemas): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Snapshot Test', version: '1.0.0'),
            components: new Components(schemas: $schemas),
        );
    }
}
