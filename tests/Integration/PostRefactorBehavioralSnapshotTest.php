<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
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
use RuntimeException;
use PostRefactorSnap_Combined;
use PostRefactorSnap_Const;
use PostRefactorSnap_Enum;
use PostRefactorSnap_MultipleOf;
use PostRefactorSnap_NestedArray;
use PostRefactorSnap_NumericRange;
use PostRefactorSnap_Pattern;
use PostRefactorSnap_SimpleObject;
use PostRefactorSnap_StringLength;
use PostRefactorSnap_UniqueItems;

/**
 * Behavioral snapshot tests for the three highest-risk Partition 3
 * refactoring zones (Oracle recommendation R1). Locks down the observable
 * behavior of ValidatorCompiler, SchemaSiblingMerger and RefResolver
 * against the pre-refactor baseline commit f5b0eb9.
 *
 * Each test exercises one representative scenario end-to-end and asserts
 * on actual output values (compiled validator runtime behavior, merged
 * Schema fields, resolved Schema identity) — never just "no exception".
 */
final class PostRefactorBehavioralSnapshotTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Zone 1: ValidatorCompiler — codegen end-to-end behavior
    // ---------------------------------------------------------------------

    #[Test]
    public function validator_compiler_simple_object_with_required_property_passes_and_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name'],
        );

        $code = $compiler->compile($schema, 'PostRefactorSnap_SimpleObject');
        $this->evalClass($code);

        $validator = new PostRefactorSnap_SimpleObject();

        // Valid: required present, types match.
        $validator->validate(['name' => 'Alice', 'age' => 30]);
        self::addToAssertionCount(1);

        // Valid: optional property omitted.
        $validator->validate(['name' => 'Bob']);
        self::addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        $validator->validate(['age' => 30]);
    }

    #[Test]
    public function validator_compiler_enum_string_accepts_member_and_rejects_outsider(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', enum: ['cat', 'dog']);

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_Enum'));
        $validator = new PostRefactorSnap_Enum();

        $validator->validate('cat');
        $validator->validate('dog');
        self::addToAssertionCount(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be one of');
        $validator->validate('lion');
    }

    #[Test]
    public function validator_compiler_const_accepts_equal_and_rejects_different(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', const: 'hello', hasConst: true);

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_Const'));
        $validator = new PostRefactorSnap_Const();

        $validator->validate('hello');
        self::addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be const');
        $validator->validate('world');
    }

    #[Test]
    public function validator_compiler_unique_items_array_accepts_unique_and_rejects_duplicates(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
            uniqueItems: true,
        );

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_UniqueItems'));
        $validator = new PostRefactorSnap_UniqueItems();

        $validator->validate([1, 2, 3]);
        self::addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        $validator->validate([1, 2, 2]);
    }

    #[Test]
    public function validator_compiler_pattern_accepts_match_and_rejects_mismatch(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_Pattern'));
        $validator = new PostRefactorSnap_Pattern();

        $validator->validate('abc');
        self::addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        $validator->validate('abc1');
    }

    #[Test]
    public function validator_compiler_multiple_of_accepts_multiple_and_rejects_non_multiple(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 0.5);

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_MultipleOf'));
        $validator = new PostRefactorSnap_MultipleOf();

        $validator->validate(2.5);
        $validator->validate(4);
        self::addToAssertionCount(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be a multiple of');
        $validator->validate(2.3);
    }

    #[Test]
    public function validator_compiler_numeric_range_enforces_minimum_maximum_and_exclusive_minimum(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'integer',
            minimum: 0,
            maximum: 100,
            exclusiveMinimum: -1,
        );

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_NumericRange'));
        $validator = new PostRefactorSnap_NumericRange();

        // In-range values pass.
        $validator->validate(0);
        $validator->validate(50);
        $validator->validate(100);
        self::addToAssertionCount(3);

        // Below minimum (0) — fails.
        try {
            $validator->validate(-1);
            self::fail('Expected RuntimeException for value below minimum');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // Hits exclusiveMinimum boundary (-1) — fails because exclusiveMinimum: -1
        // means values <= -1 are rejected; -1 itself must be rejected.
        try {
            $validator->validate(-1);
            self::fail('Expected RuntimeException at exclusiveMinimum boundary');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // Above maximum — fails.
        try {
            $validator->validate(101);
            self::fail('Expected RuntimeException above maximum');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    #[Test]
    public function validator_compiler_string_length_enforces_min_and_max_bounds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', minLength: 1, maxLength: 5);

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_StringLength'));
        $validator = new PostRefactorSnap_StringLength();

        $validator->validate('a');
        $validator->validate('hello');
        self::addToAssertionCount(2);

        // Empty string violates minLength=1.
        try {
            $validator->validate('');
            self::fail('Expected RuntimeException for empty string');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // 6 chars exceeds maxLength=5.
        try {
            $validator->validate('toolong');
            self::fail('Expected RuntimeException for too-long string');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    #[Test]
    public function validator_compiler_nested_object_in_array_validates_each_item(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: ['id' => new Schema(type: 'integer')],
                required: ['id'],
            ),
        );

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_NestedArray'));
        $validator = new PostRefactorSnap_NestedArray();

        $validator->validate([['id' => 1], ['id' => 2]]);
        self::addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        $validator->validate([['id' => 1], ['name' => 'missing id']]);
    }

    #[Test]
    public function validator_compiler_combined_schema_enforces_all_supported_keywords(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            required: ['kind', 'count', 'tags'],
            properties: [
                'kind' => new Schema(type: 'string', enum: ['alpha', 'beta']),
                'count' => new Schema(type: 'integer', minimum: 0, maximum: 100, multipleOf: 2),
                'label' => new Schema(type: 'string', const: 'fixed', hasConst: true),
                'code' => new Schema(type: 'string', pattern: '^[A-Z]{3}$'),
                'name' => new Schema(type: 'string', minLength: 1, maxLength: 10),
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                    uniqueItems: true,
                ),
            ],
        );

        $this->evalClass($compiler->compile($schema, 'PostRefactorSnap_Combined'));
        $validator = new PostRefactorSnap_Combined();

        // Fully-valid payload.
        $validator->validate([
            'kind' => 'alpha',
            'count' => 50,
            'label' => 'fixed',
            'code' => 'ABC',
            'name' => 'hello',
            'tags' => ['x', 'y'],
        ]);
        self::addToAssertionCount(1);

        // Enum violation.
        try {
            $validator->validate([
                'kind' => 'gamma',
                'count' => 50,
                'label' => 'fixed',
                'code' => 'ABC',
                'name' => 'hello',
                'tags' => ['x', 'y'],
            ]);
            self::fail('Expected RuntimeException for enum violation');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // multipleOf violation.
        try {
            $validator->validate([
                'kind' => 'alpha',
                'count' => 51,
                'label' => 'fixed',
                'code' => 'ABC',
                'name' => 'hello',
                'tags' => ['x', 'y'],
            ]);
            self::fail('Expected RuntimeException for multipleOf violation');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // uniqueItems violation.
        try {
            $validator->validate([
                'kind' => 'alpha',
                'count' => 50,
                'label' => 'fixed',
                'code' => 'ABC',
                'name' => 'hello',
                'tags' => ['x', 'x'],
            ]);
            self::fail('Expected RuntimeException for uniqueItems violation');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        // Missing required.
        try {
            $validator->validate([
                'kind' => 'alpha',
                'count' => 50,
                'tags' => ['x'],
            ]);
            self::fail('Expected RuntimeException for missing required');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    // ---------------------------------------------------------------------
    // Zone 2: SchemaSiblingMerger — merge semantics
    // ---------------------------------------------------------------------

    #[Test]
    public function sibling_merger_nullable_widening_semantics_sibling_false_keeps_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: false);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        // Widening semantics: a `nullable` sibling next to `$ref` can only add
        // null to the target. `nullable: false` is indistinguishable from an
        // omitted flag, so it never removes the target's nullability.
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
    public function sibling_merger_type_conflict_clears_type_and_wraps_in_allOf(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(type: 'integer');

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        // Disjoint types → type cleared, both constraints wrapped in allOf.
        self::assertNull($merged->type);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame('string', $merged->allOf[0]->type);
        self::assertSame('integer', $merged->allOf[1]->type);
    }

    #[Test]
    public function sibling_merger_format_conflict_clears_format_and_wraps_in_allOf(): void
    {
        $resolved = new Schema(type: 'string', format: 'email');
        $sibling = new Schema(format: 'uri');

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        // Different formats → format cleared, both wrapped in allOf.
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

        // Intersection is just 'string'.
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
    public function sibling_merger_anyOf_on_both_sides_wraps_in_allOf_not_concatenates(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolvedB = new Schema(type: 'integer');
        $siblingC = new Schema(type: 'boolean');
        $siblingD = new Schema(type: 'null');

        $resolved = new Schema(anyOf: [$resolvedA, $resolvedB]);
        $sibling = new Schema(anyOf: [$siblingC, $siblingD]);

        $merged = new SchemaSiblingMerger()->merge($resolved, $sibling);

        // anyOf is wrapped — both branches must hold (AND semantics), not
        // concatenated (which would be OR over 4 alternatives).
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

        // AND of two ranges = stricter bounds win.
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

    // ---------------------------------------------------------------------
    // Zone 3: RefResolver — $ref resolution semantics
    // ---------------------------------------------------------------------

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
        // Property still holds the $ref pointer; resolve() itself returns
        // the named schema unchanged — its siblings are not auto-resolved.
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

        // Same identity — cache returns the previously resolved instance.
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

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

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

    /**
     * Eval a freshly-compiled validator class. Mirrors the existing
     * pattern in tests/Unit/Compiler/ValidatorCompilerTest.php — eval
     * always defines the class in the global namespace regardless of
     * the surrounding namespace context.
     */
    private function evalClass(string $code): void
    {
        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);
    }
}
