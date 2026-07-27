<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\UnsupportedKeywordDetector;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the UnsupportedKeywordDetector precondition check.
 *
 * The detector walks the schema tree and reports every keyword the
 * ValidatorCompiler does not generate code for. Detection is recursive —
 * unsupported keywords inside nested `properties` or `items` are also
 * reported, so the compiler never silently emits a validator that
 * ignores them (R4-CORRECTNESS-004).
 */
final class UnsupportedKeywordDetectorTest extends TestCase
{
    private UnsupportedKeywordDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new UnsupportedKeywordDetector();
    }

    #[Test]
    public function detect_returns_empty_list_for_supported_keywords_only(): void
    {
        // Every keyword the compiler handles (R4-CORRECTNESS-004) must
        // produce an empty detection list.
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 1, maxLength: 10, pattern: '^[a-z]+$'),
                'age' => new Schema(type: 'integer', minimum: 0, maximum: 120, multipleOf: 2),
                'tags' => new Schema(type: 'array', minItems: 1, maxItems: 10, uniqueItems: true, items: new Schema(type: 'string')),
                'status' => new Schema(enum: ['active', 'archived']),
                'constant' => new Schema(const: 'fixed', hasConst: true),
            ],
            required: ['name'],
            additionalProperties: false,
        );

        self::assertSame([], $this->detector->detect($schema));
    }

    #[Test]
    public function detect_returns_all_composition_keywords_at_root(): void
    {
        $schema = new Schema(
            allOf: [new Schema()],
            anyOf: [new Schema()],
            oneOf: [new Schema()],
            not: new Schema(),
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('allOf', $detected);
        self::assertContains('anyOf', $detected);
        self::assertContains('oneOf', $detected);
        self::assertContains('not', $detected);
    }

    #[Test]
    public function detect_returns_conditional_keywords(): void
    {
        $schema = new Schema(
            if: new Schema(),
            then: new Schema(),
            else: new Schema(),
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('if', $detected);
        self::assertContains('then', $detected);
        self::assertContains('else', $detected);
    }

    #[Test]
    public function detect_returns_pattern_properties_format_min_max_properties(): void
    {
        $schema = new Schema(
            patternProperties: ['^x-' => new Schema()],
            format: 'email',
            minProperties: 1,
            maxProperties: 10,
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('patternProperties', $detected);
        self::assertContains('format', $detected);
        self::assertContains('minProperties', $detected);
        self::assertContains('maxProperties', $detected);
    }

    #[Test]
    public function detect_returns_additional_properties_schema_form_but_not_bool_form(): void
    {
        // additionalProperties: true|false is supported (bool form).
        // additionalProperties: Schema is unsupported.
        $unsupported = new Schema(additionalProperties: new Schema(type: 'string'));
        $supportedTrue = new Schema(additionalProperties: true);
        $supportedFalse = new Schema(additionalProperties: false);

        self::assertContains('additionalProperties', $this->detector->detect($unsupported));
        self::assertSame([], $this->detector->detect($supportedTrue));
        self::assertSame([], $this->detector->detect($supportedFalse));
    }

    #[Test]
    public function detect_returns_prefix_items_contains_property_names(): void
    {
        $schema = new Schema(
            prefixItems: [new Schema()],
            contains: new Schema(),
            propertyNames: new Schema(),
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('prefixItems', $detected);
        self::assertContains('contains', $detected);
        self::assertContains('propertyNames', $detected);
    }

    #[Test]
    public function detect_returns_unevaluated_and_dependent_schemas(): void
    {
        $schema = new Schema(
            unevaluatedItems: new Schema(),
            unevaluatedProperties: new Schema(),
            dependentSchemas: ['foo' => new Schema()],
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('unevaluatedItems', $detected);
        self::assertContains('unevaluatedProperties', $detected);
        self::assertContains('dependentSchemas', $detected);
    }

    #[Test]
    public function detect_returns_discriminator_and_content_keywords(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(propertyName: 'type'),
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(),
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('discriminator', $detected);
        self::assertContains('contentEncoding', $detected);
        self::assertContains('contentMediaType', $detected);
        self::assertContains('contentSchema', $detected);
    }

    #[Test]
    public function detect_returns_boolean_form_keywords(): void
    {
        // Boolean form of items/contains/propertyNames/if/then/else/not/unevaluatedItems
        // is unsupported even though the Schema form is also unsupported
        // for some of them. Each gets a distinct label so the
        // UnsupportedKeywordException message is actionable.
        $schema = new Schema(
            items: true,
            contains: true,
            propertyNames: true,
            if: true,
            then: true,
            else: true,
            not: true,
            unevaluatedItems: true,
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('items (boolean form)', $detected);
        self::assertContains('contains (boolean form)', $detected);
        self::assertContains('propertyNames (boolean form)', $detected);
        self::assertContains('if (boolean form)', $detected);
        self::assertContains('then (boolean form)', $detected);
        self::assertContains('else (boolean form)', $detected);
        self::assertContains('not (boolean form)', $detected);
        self::assertContains('unevaluatedItems (boolean form)', $detected);
    }

    #[Test]
    public function detect_walks_nested_properties(): void
    {
        // R4-CORRECTNESS-004: unsupported keywords inside nested
        // `properties` are reported at compile time, not silently
        // ignored by the generated validator.
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'email' => new Schema(type: 'string', format: 'email'),
                    ],
                ),
            ],
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('format', $detected);
    }

    #[Test]
    public function detect_walks_nested_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'array',
                items: new Schema(prefixItems: [new Schema()]),
            ),
        );

        $detected = $this->detector->detect($schema);

        self::assertContains('prefixItems', $detected);
    }

    #[Test]
    public function detect_deduplicates_keyword_reports_across_tree(): void
    {
        // Two `format` keywords at different depths must yield a single
        // 'format' entry, not two. The dedupe is `array_values(array_unique())`.
        $schema = new Schema(
            type: 'object',
            properties: [
                'a' => new Schema(type: 'string', format: 'email'),
                'b' => new Schema(type: 'string', format: 'uri'),
            ],
        );

        $detected = $this->detector->detect($schema);

        self::assertSame(['format'], $detected);
    }

    #[Test]
    public function detect_returns_empty_list_for_empty_schema(): void
    {
        self::assertSame([], $this->detector->detect(new Schema()));
    }
}
