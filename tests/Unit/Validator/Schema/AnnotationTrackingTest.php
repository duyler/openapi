<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

/**
 * End-to-end coverage for JSON Schema 2020-12 annotation passing
 * through in-place applicators (R3-SPEC-001 / R3-SPEC-002 /
 * R3-SPEC-003 / R3-SPEC-004).
 *
 * Each test drives a full SchemaValidatorWithContext validate() call
 * so that the composition validators fork+merge child annotations and
 * the unevaluated* validators observe them at decision time.
 */
#[CoversClass(SchemaValidatorWithContext::class)]
final class AnnotationTrackingTest extends TestCase
{
    private RefResolver $refResolver;
    private ValidatorPool $pool;
    private StatelessValidatorRegistry $statelessValidators;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Annotation Tracking API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Base' => new Schema(
                        type: 'object',
                        properties: [
                            'id' => new Schema(type: 'integer'),
                        ],
                    ),
                ],
            ),
        );
    }

    #[Test]
    public function unevaluated_properties_with_all_of_evaluates_branch_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
            allOf: [
                new Schema(properties: ['name' => new Schema(type: 'string')]),
            ],
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['name' => 'Alice'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('allOf-validated "name" must be evaluated; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_any_of_evaluates_matched_branch(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
            anyOf: [
                new Schema(properties: ['a' => new Schema(type: 'integer')]),
                new Schema(properties: ['b' => new Schema(type: 'integer')]),
            ],
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['a' => 1], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('anyOf-validated "a" must be evaluated; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_any_of_fails_on_unmatched_extra(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
            anyOf: [
                new Schema(properties: ['a' => new Schema(type: 'integer')]),
                new Schema(properties: ['b' => new Schema(type: 'integer')]),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->createValidator()->validate(['a' => 1, 'c' => 2], $schema);
    }

    #[Test]
    public function unevaluated_properties_with_one_of_picks_winning_branch(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
            oneOf: [
                new Schema(properties: ['a' => new Schema(type: 'integer')], required: ['a']),
                new Schema(properties: ['b' => new Schema(type: 'integer')], required: ['b']),
            ],
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['a' => 1], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('oneOf-validated "a" must be evaluated; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_ref_sibling(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/Base',
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['id' => 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('$ref-validated "id" must be evaluated; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_if_then_evaluates_then_branch(): void
    {
        $schema = new Schema(
            type: 'object',
            if: new Schema(
                required: ['kind'],
                properties: ['kind' => new Schema(type: 'string', enum: ['extended'])],
            ),
            then: new Schema(properties: ['extra' => new Schema(type: 'string')]),
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['kind' => 'extended', 'extra' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('then-branch "extra" must be evaluated when if matches; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_with_prefix_items_and_items_schema(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'string')],
            items: new Schema(type: 'integer'),
            unevaluatedItems: new Schema(type: 'boolean'),
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate(['foo', 1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('prefixItems + items evaluate all indices; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_with_contains_evaluates_matched_indices_only(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer', minimum: 0),
            unevaluatedItems: new Schema(type: 'boolean'),
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('contains matches integers 1,2,3 — all evaluated; unevaluatedItems never reached; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_with_all_of_items_schema(): void
    {
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: new Schema(type: 'boolean'),
            allOf: [
                new Schema(items: new Schema(type: 'integer')),
            ],
        );

        $succeeded = false;

        try {
            $this->createValidator()->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('allOf items-schema evaluates every index; got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_fails_on_truly_unevaluated(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['known' => new Schema(type: 'string')],
            unevaluatedProperties: false,
        );

        $this->expectException(ValidationException::class);

        $this->createValidator()->validate(['unknown' => 1], $schema);
    }

    #[Test]
    public function not_branch_does_not_contribute_to_evaluated_set(): void
    {
        $schema = new Schema(
            type: 'object',
            not: new Schema(required: ['forbidden']),
            unevaluatedProperties: false,
        );

        $this->expectException(ValidationException::class);

        $this->createValidator()->validate(['any_property' => 1], $schema);
    }

    private function createValidator(): SchemaValidatorWithContext
    {
        return new SchemaValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );
    }
}
