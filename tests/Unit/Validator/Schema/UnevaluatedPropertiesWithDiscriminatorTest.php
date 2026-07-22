<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Regression coverage for R4-CORRECTNESS-005 / R4-SPEC-015:
 * discriminator-routed sub-validation must propagate evaluated-property /
 * evaluated-item annotations to the parent ValidationContext via
 * forkForBranch + mergeChildAnnotations so that adjacent
 * unevaluatedProperties:false / unevaluatedItems:false constraints honour
 * properties already validated by the discriminator target schema
 * (JSON Schema 2020-12 §10.3.4 / §11.1.1.3).
 */
#[CoversClass(DiscriminatorValidator::class)]
final class UnevaluatedPropertiesWithDiscriminatorTest extends TestCase
{
    private RefResolver $refResolver;
    private ValidatorPool $pool;
    private StatelessValidatorRegistry $statelessValidators;
    private DiscriminatorValidator $discriminatorValidator;

    #[Override]
    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->discriminatorValidator = new DiscriminatorValidator(
            new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );
    }

    #[Test]
    public function discriminator_target_properties_are_propagated_to_parent_context(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
            unevaluatedProperties: false,
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Cat' => $catSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $this->discriminatorValidator->validate(
            ['petType' => 'cat', 'name' => 'Whiskers'],
            $petSchema,
            $document,
            '/',
            $parentContext,
        );

        self::assertTrue(
            $parentContext->hasPropertyBeenEvaluated('petType'),
            'discriminator property "petType" must be marked evaluated by target sub-validation',
        );
        self::assertTrue(
            $parentContext->hasPropertyBeenEvaluated('name'),
            'target-schema property "name" must be marked evaluated by target sub-validation',
        );
    }

    #[Test]
    public function discriminator_property_alone_is_evaluated_when_target_has_no_other_properties(): void
    {
        $targetSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
            ],
        );

        $parentSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Target'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Target')],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Parent' => $parentSchema, 'Target' => $targetSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $this->discriminatorValidator->validate(
            ['petType' => 'cat'],
            $parentSchema,
            $document,
            '/',
            $parentContext,
        );

        self::assertTrue($parentContext->hasPropertyBeenEvaluated('petType'));
    }

    #[Test]
    public function additional_properties_integer_in_target_are_propagated_to_parent(): void
    {
        $targetSchema = new Schema(
            type: 'object',
            properties: [
                'kind' => new Schema(type: 'string', enum: ['numbered']),
            ],
            additionalProperties: new Schema(type: 'integer'),
        );

        $parentSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'kind',
                mapping: ['numbered' => '#/components/schemas/Target'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Target')],
            unevaluatedProperties: false,
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Parent' => $parentSchema, 'Target' => $targetSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $this->discriminatorValidator->validate(
            ['kind' => 'numbered', 'extra' => 42],
            $parentSchema,
            $document,
            '/',
            $parentContext,
        );

        self::assertTrue($parentContext->hasPropertyBeenEvaluated('kind'));
        self::assertTrue($parentContext->hasPropertyBeenEvaluated('extra'));
    }

    #[Test]
    public function invalid_target_input_still_throws_type_mismatch_error(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Cat' => $catSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $this->expectException(ValidationException::class);

        try {
            $this->discriminatorValidator->validate(
                ['petType' => 'cat', 'name' => 42],
                $petSchema,
                $document,
                '/',
                $parentContext,
            );
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertGreaterThan(0, $errors);
            self::assertInstanceOf(TypeMismatchError::class, $errors[0]);

            throw $e;
        }
    }

    #[Test]
    public function failed_target_validation_does_not_merge_annotations_into_parent(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Cat' => $catSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $caught = null;

        try {
            $this->discriminatorValidator->validate(
                ['petType' => 'cat', 'name' => 42],
                $petSchema,
                $document,
                '/',
                $parentContext,
            );
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'invalid target input must still raise');
        self::assertFalse(
            $parentContext->hasPropertyBeenEvaluated('petType'),
            'failed target sub-validation must not contribute annotations to parent context',
        );
        self::assertFalse(
            $parentContext->hasPropertyBeenEvaluated('name'),
            'failed target sub-validation must not contribute annotations to parent context',
        );
    }

    #[Test]
    public function parent_depth_is_unchanged_after_discriminator_routing(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Cat' => $catSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);
        $parentContext->incrementDepth();
        $depthBefore = $parentContext->depth();

        $this->discriminatorValidator->validate(
            ['petType' => 'cat', 'name' => 'Whiskers'],
            $petSchema,
            $document,
            '/',
            $parentContext,
        );

        self::assertSame(
            $depthBefore,
            $parentContext->depth(),
            'parent depth must be unchanged after discriminator routing (forkForBranch copies depth, child changes do not leak)',
        );
    }

    #[Test]
    public function end_to_end_validation_with_canonical_spec_succeeds(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['cat' => '#/components/schemas/Cat'],
            ),
            oneOf: [new Schema(ref: '#/components/schemas/Cat')],
            unevaluatedProperties: false,
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Cat' => $catSchema]),
        );

        $validator = new SchemaValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $succeeded = false;

        try {
            $validator->validate(['petType' => 'cat', 'name' => 'Whiskers'], $petSchema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(
                sprintf('canonical Pet+Cat input must validate; got %s: %s', $e::class, $e->getMessage()),
            );
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function default_mapping_route_propagates_annotations_to_parent_context(): void
    {
        $genericSchema = new Schema(
            type: 'object',
            required: ['anything'],
            properties: [
                'anything' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            discriminator: new Discriminator(defaultMapping: '#/components/schemas/Generic'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Pet' => $petSchema, 'Generic' => $genericSchema]),
        );

        $parentContext = ValidationContext::create($this->pool);

        $this->discriminatorValidator->validate(
            ['anything' => 'value'],
            $petSchema,
            $document,
            '/',
            $parentContext,
        );

        self::assertTrue(
            $parentContext->hasPropertyBeenEvaluated('anything'),
            'defaultMapping route must also propagate annotations to parent context',
        );
    }

    #[Test]
    public function end_to_end_unevaluated_property_truly_unmatched_still_reports(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['known' => new Schema(type: 'string')],
            unevaluatedProperties: false,
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(schemas: ['Static' => $schema]),
        );

        $validator = new SchemaValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        try {
            $validator->validate(['unknown' => 1], $schema);
            self::fail('truly unmatched property must raise UnevaluatedPropertyError via ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertGreaterThan(0, $errors);
            self::assertInstanceOf(UnevaluatedPropertyError::class, $errors[0]);
        }
    }
}
