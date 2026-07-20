<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the override-where-not-null contract of {@see Schema::withOverrides()}.
 *
 * `withOverrides` uses `$arg ?? $this->arg`, so for nullable parameters a
 * passed `null` means "keep the original", while a non-null value replaces it.
 * For booleans (typed `?bool` in withOverrides vs `bool` in __construct), an
 * explicit `false` IS an override, not a fallback — this is the only way to
 * flip a `true` back to `false` on an immutable DTO.
 */
#[CoversClass(Schema::class)]
final class SchemaWithOverridesTest extends TestCase
{
    #[Test]
    public function with_overrides_no_args_returns_equivalent_schema(): void
    {
        $original = new Schema(
            type: 'object',
            title: 'User',
            properties: ['id' => new Schema(type: 'integer')],
            required: ['id'],
            deprecated: true,
            hasDefault: true,
            default: 'fallback',
            nullable: true,
        );

        $result = $original->withOverrides();

        // Equivalent field-for-field — but a distinct immutable instance.
        self::assertNotSame($original, $result);
        self::assertSame('object', $result->type);
        self::assertSame('User', $result->title);
        self::assertSame(['id'], $result->required);
        self::assertTrue($result->deprecated);
        self::assertTrue($result->hasDefault);
        self::assertSame('fallback', $result->default);
        self::assertTrue($result->nullable);
    }

    #[Test]
    public function with_overrides_replaces_scalar_fields(): void
    {
        $original = new Schema(type: 'string', title: 'Old', minLength: 1);

        $result = $original->withOverrides(type: 'integer', title: 'New', minLength: 5);

        self::assertSame('integer', $result->type);
        self::assertSame('New', $result->title);
        self::assertSame(5, $result->minLength);
    }

    #[Test]
    public function with_overrides_null_keeps_original_for_nullable_scalar(): void
    {
        $original = new Schema(type: 'string', title: 'Keep', pattern: '^[a-z]+$');

        // Explicit null on a nullable parameter means "no override".
        $result = $original->withOverrides(type: null, title: null, pattern: null);

        self::assertSame('string', $result->type);
        self::assertSame('Keep', $result->title);
        self::assertSame('^[a-z]+$', $result->pattern);
    }

    #[Test]
    public function with_overrides_false_flips_boolean_back_to_false(): void
    {
        // Adversarial guard: `false ?? $this->x` evaluates to `false`, NOT to
        // `$this->x`. If anyone "simplifies" withOverrides to use
        // `func_get_args` or argument-count checks, this test catches it.
        $original = new Schema(
            deprecated: true,
            readOnly: true,
            writeOnly: true,
            hasDefault: true,
            hasConst: true,
            nullable: true,
        );

        $result = $original->withOverrides(
            deprecated: false,
            readOnly: false,
            writeOnly: false,
            hasDefault: false,
            hasConst: false,
            nullable: false,
        );

        self::assertFalse($result->deprecated);
        self::assertFalse($result->readOnly);
        self::assertFalse($result->writeOnly);
        self::assertFalse($result->hasDefault);
        self::assertFalse($result->hasConst);
        self::assertFalse($result->nullable);
    }

    #[Test]
    public function with_overrides_null_keeps_true_boolean(): void
    {
        $original = new Schema(
            deprecated: true,
            readOnly: true,
            writeOnly: true,
            hasDefault: true,
            hasConst: true,
            nullable: true,
        );

        // null on ?bool means "no override" — `true` survives.
        $result = $original->withOverrides(
            deprecated: null,
            readOnly: null,
            writeOnly: null,
            hasDefault: null,
            hasConst: null,
            nullable: null,
        );

        self::assertTrue($result->deprecated);
        self::assertTrue($result->readOnly);
        self::assertTrue($result->writeOnly);
        self::assertTrue($result->hasDefault);
        self::assertTrue($result->hasConst);
        self::assertTrue($result->nullable);
    }

    #[Test]
    public function with_overrides_replaces_nested_schema_and_collections(): void
    {
        $originalItems = new Schema(type: 'string');
        $original = new Schema(
            type: 'array',
            items: $originalItems,
            allOf: [new Schema(type: 'object')],
            properties: ['a' => new Schema(type: 'string')],
        );

        $newItems = new Schema(type: 'integer');
        $newAllOf = [new Schema(type: 'number')];
        $newProperties = ['b' => new Schema(type: 'boolean')];

        $result = $original->withOverrides(
            items: $newItems,
            allOf: $newAllOf,
            properties: $newProperties,
        );

        self::assertSame($newItems, $result->items);
        self::assertSame($newAllOf, $result->allOf);
        self::assertSame($newProperties, $result->properties);
    }

    #[Test]
    public function with_overrides_ref_family_replaces_ref(): void
    {
        $original = new Schema(ref: '#/components/schemas/Old');

        $result = $original->withOverrides(
            ref: '#/components/schemas/New',
            refSummary: 'New summary',
            refDescription: 'New description',
        );

        self::assertSame('#/components/schemas/New', $result->ref);
        self::assertSame('New summary', $result->refSummary);
        self::assertSame('New description', $result->refDescription);
    }

    #[Test]
    public function with_overrides_replaces_schema_or_bool_union_fields(): void
    {
        $original = new Schema(
            type: 'object',
            additionalProperties: true,
            unevaluatedProperties: new Schema(type: 'string'),
            contentSchema: false,
        );

        $newAdditional = new Schema(type: 'integer');
        $result = $original->withOverrides(
            additionalProperties: $newAdditional,
            unevaluatedProperties: false,
            contentSchema: true,
        );

        self::assertSame($newAdditional, $result->additionalProperties);
        self::assertFalse($result->unevaluatedProperties);
        self::assertTrue($result->contentSchema);
    }

    #[Test]
    public function with_overrides_replaces_discriminator_and_xml(): void
    {
        $original = new Schema(
            xml: new Xml(name: 'old'),
            discriminator: new Discriminator(propertyName: 'type', mapping: ['a' => '#/A']),
        );

        $newXml = new Xml(name: 'new', wrapped: true);
        $newDiscriminator = new Discriminator(propertyName: 'kind');

        $result = $original->withOverrides(xml: $newXml, discriminator: $newDiscriminator);

        self::assertSame($newXml, $result->xml);
        self::assertSame($newDiscriminator, $result->discriminator);
    }

    #[Test]
    public function with_overrides_null_keeps_original_for_default_and_const(): void
    {
        // Adversarial guard: even though `default` and `const` accept `null`
        // as a valid value at construction, withOverrides uses `$x ?? $this->x`,
        // and `??` treats null as "no override" regardless of parameter type.
        // This pins the uniform ?? semantics: null never overrides.
        $original = new Schema(
            type: 'string',
            default: 'keep-default',
            hasDefault: true,
            const: 'keep-const',
            hasConst: true,
        );

        $result = $original->withOverrides(default: null, const: null);

        self::assertSame('keep-default', $result->default);
        self::assertSame('keep-const', $result->const);
        self::assertTrue($result->hasDefault);
        self::assertTrue($result->hasConst);
    }

    #[Test]
    public function with_overrides_replaces_default_with_explicit_value(): void
    {
        $original = new Schema(
            type: 'string',
            default: 'old',
            hasDefault: true,
        );

        // A non-null value overrides, including empty string / empty array.
        $result = $original->withOverrides(default: '', hasDefault: false);

        self::assertSame('', $result->default);
        self::assertFalse($result->hasDefault);
    }

    #[Test]
    public function with_overrides_is_immutable_original_unchanged(): void
    {
        $original = new Schema(type: 'string', title: 'Original');

        $result = $original->withOverrides(type: 'integer');

        self::assertSame('string', $original->type);
        self::assertSame('Original', $original->title);
        self::assertSame('integer', $result->type);
        self::assertSame('Original', $result->title);
    }

    #[Test]
    public function with_overrides_chains(): void
    {
        $schema = new Schema(type: 'object');

        $chained = $schema
            ->withOverrides(title: 'First')
            ->withOverrides(description: 'Second')
            ->withOverrides(deprecated: true);

        self::assertSame('object', $chained->type);
        self::assertSame('First', $chained->title);
        self::assertSame('Second', $chained->description);
        self::assertTrue($chained->deprecated);
    }
}
