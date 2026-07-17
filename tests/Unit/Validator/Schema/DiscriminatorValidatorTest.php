<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function count;

/**
 * @internal
 */
#[CoversClass(DiscriminatorValidator::class)]
final class DiscriminatorValidatorTest extends TestCase
{
    private DiscriminatorValidator $validator;
    private RefResolver $refResolver;
    private ValidatorPool $pool;
    private StatelessValidatorRegistry $statelessValidators;

    #[Override]
    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->validator = new DiscriminatorValidator(
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );
    }

    #[Test]
    public function implicit_mapping_without_title_selects_schema_by_ref_name(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Implicit mapping must select Cat by $ref name even without title');
    }

    #[Test]
    public function implicit_mapping_ignores_title_when_title_differs_from_name(): void
    {
        $catSchema = new Schema(
            title: 'My Cat Schema',
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Implicit mapping must ignore title and match by schema name');
    }

    #[Test]
    public function explicit_mapping_takes_priority_over_implicit_name_match(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['kitten' => '#/components/schemas/Cat'],
            ),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $kittenData = ['petType' => 'kitten', 'name' => 'Tom'];

        $exception = null;

        try {
            $this->validator->validate($kittenData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Explicit mapping must take priority over implicit name match');
    }

    #[Test]
    public function unknown_discriminator_value_throws_exception(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $this->expectException(UnknownDiscriminatorValueException::class);
        $this->expectExceptionMessage('Unknown discriminator value "Bird"');

        $this->validator->validate(['petType' => 'Bird'], $petSchema, $document);
    }

    #[Test]
    public function parent_required_property_enforced_after_child_match(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catWithoutMeow = ['petType' => 'Cat', 'name' => 'Tom'];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema selected via implicit name mapping should enforce its required fields');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function default_mapping_used_when_no_explicit_or_implicit_match(): void
    {
        $defaultSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            discriminator: new Discriminator(
                propertyName: 'petType',
                defaultMapping: '#/components/schemas/Default',
            ),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Default' => $defaultSchema,
                ],
            ),
        );

        $unknownData = ['petType' => 'unknown', 'name' => 'Tom'];

        $exception = null;

        try {
            $this->validator->validate($unknownData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'defaultMapping must resolve unknown values when no explicit/implicit match exists');
    }

    #[Test]
    public function inline_schema_without_ref_in_oneOf_is_skipped_during_implicit_mapping(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(type: 'object', properties: ['noise' => new Schema(type: 'string')]),
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Inline schema without $ref must be skipped during implicit mapping');
    }

    #[Test]
    public function discriminator_with_allOf_ref_resolves_correctly(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            discriminator: new Discriminator(propertyName: 'petType'),
            allOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat'];

        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Discriminator must resolve $refs inside allOf composition');
    }

    #[Test]
    public function discriminator_with_allOf_and_nested_oneOf(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            discriminator: new Discriminator(propertyName: 'petType'),
            allOf: [
                new Schema(
                    oneOf: [
                        new Schema(ref: '#/components/schemas/Cat'),
                        new Schema(ref: '#/components/schemas/Dog'),
                    ],
                ),
            ],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat'];

        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Discriminator must recurse through allOf into nested oneOf');
    }

    #[Test]
    public function discriminator_with_allOf_unknown_value_throws_unknown_exception(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            discriminator: new Discriminator(propertyName: 'petType'),
            allOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $this->expectException(UnknownDiscriminatorValueException::class);
        $this->expectExceptionMessage('Unknown discriminator value "Bird"');

        $this->validator->validate(['petType' => 'Bird'], $petSchema, $document);
    }
}
