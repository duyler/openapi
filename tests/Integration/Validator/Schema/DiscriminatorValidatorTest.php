<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function count;

/**
 * @internal
 */
#[CoversClass(DiscriminatorValidator::class)]
final class DiscriminatorValidatorTest extends TestCase
{
    private DiscriminatorValidator $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;
    private StatelessValidatorRegistry $statelessValidators;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->validator = new DiscriminatorValidator(dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));
    }

    #[Test]
    public function validate_with_mapping(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'meow' => true,
        ];

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'bark' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);
        $this->validator->validate($dogData, $petSchema, $document);

        $catWithDogField = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'bark' => true,
        ];

        try {
            $this->validator->validate($catWithDogField, $petSchema, $document);
            $this->fail('Cat schema should require "meow" field');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function throw_error_for_missing_property(): void
    {
        $catSchema = new Schema(title: 'Cat');
        $dogSchema = new Schema(title: 'Dog');

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $this->expectException(MissingDiscriminatorPropertyException::class);
        $this->expectExceptionMessage('Missing required discriminator property "petType"');

        $this->validator->validate(
            ['name' => 'Fluffy'],
            $petSchema,
            $document,
        );
    }

    #[Test]
    public function throw_error_for_invalid_type(): void
    {
        $catSchema = new Schema(title: 'Cat');
        $dogSchema = new Schema(title: 'Dog');

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $this->expectException(DiscriminatorMismatchException::class);
        $this->expectExceptionMessage('Discriminator expects object at property "petType", but got string');

        $this->validator->validate('invalid', $petSchema, $document);
    }

    #[Test]
    public function throw_error_for_non_string_discriminator_value(): void
    {
        $catSchema = new Schema(title: 'Cat');
        $dogSchema = new Schema(title: 'Dog');

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $this->expectException(InvalidDiscriminatorValueException::class);
        $this->expectExceptionMessage('Discriminator property "petType" must be string, but got int');

        $this->validator->validate(
            ['petType' => 123],
            $petSchema,
            $document,
        );
    }

    #[Test]
    public function throw_error_for_unknown_value(): void
    {
        $catSchema = new Schema(title: 'Cat');
        $dogSchema = new Schema(title: 'Dog');

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
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
        $this->expectExceptionMessage('Unknown discriminator value "bird"');

        $this->validator->validate(
            ['petType' => 'bird'],
            $petSchema,
            $document,
        );
    }

    #[Test]
    public function skip_validation_when_no_discriminator(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['mustExist'],
            properties: [
                'mustExist' => new Schema(type: 'string'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $exception = null;

        try {
            $this->validator->validate(['data' => 'value'], $schema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Validation should be skipped when schema has no discriminator');
    }

    #[Test]
    public function validate_without_mapping_using_title(): void
    {
        $catSchema = new Schema(
            title: 'cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema selected by title should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_one_of(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $dogData = [
            'name' => 'Rex',
            'petType' => 'Dog',
            'bark' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);
        $this->validator->validate($dogData, $petSchema, $document);

        $catWithBarkInsteadOfMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'bark' => true,
        ];

        try {
            $this->validator->validate($catWithBarkInsteadOfMeow, $petSchema, $document);
            $this->fail('Cat schema selected by title should require "meow", not "bark"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_any_of(): void
    {
        $catSchema = new Schema(
            title: 'cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'dog',
            type: 'object',
            required: ['petType', 'name', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            anyOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'meow' => true,
        ];

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'bark' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);
        $this->validator->validate($dogData, $petSchema, $document);

        $catWithBarkInsteadOfMeow = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'bark' => true,
        ];

        try {
            $this->validator->validate($catWithBarkInsteadOfMeow, $petSchema, $document);
            $this->fail('Cat schema selected via anyOf should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_oneOf_and_discriminator_no_mapping(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $validData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $this->validator->validate($validData, $petSchema, $document);

        $dataWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $this->validator->validate($dataWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_custom_data_path(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document, '/custom/path');

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document, '/custom/path');
            $this->fail('Cat schema should require "meow" at custom path');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_empty_mapping(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema found via title with empty mapping should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_schema_without_title(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            anyOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema selected via mapping should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_finds_schema_by_title(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $dogData = [
            'name' => 'Rex',
            'petType' => 'Dog',
            'bark' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);
        $this->validator->validate($dogData, $petSchema, $document);

        $catWithBarkInsteadOfMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'bark' => true,
        ];

        try {
            $this->validator->validate($catWithBarkInsteadOfMeow, $petSchema, $document);
            $this->fail('Cat schema found by title should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_fallback_to_default_mapping(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $unknownPetSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'unknownTrait'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'unknownTrait' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                ],
                defaultMapping: '#/components/schemas/UnknownPet',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'UnknownPet' => $unknownPetSchema,
                ],
            ),
        );

        $birdData = [
            'name' => 'Tweety',
            'petType' => 'bird',
            'unknownTrait' => 'can-fly',
        ];

        $this->validator->validate($birdData, $petSchema, $document);

        $birdWithoutUnknownTrait = [
            'name' => 'Tweety',
            'petType' => 'bird',
        ];

        try {
            $this->validator->validate($birdWithoutUnknownTrait, $petSchema, $document);
            $this->fail('UnknownPet schema selected via defaultMapping should require "unknownTrait"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('unknownTrait', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_without_property_name_uses_default_mapping(): void
    {
        $fallbackSchema = new Schema(
            type: 'object',
            required: ['name', 'fallbackId'],
            properties: [
                'name' => new Schema(type: 'string'),
                'fallbackId' => new Schema(type: 'integer'),
            ],
        );

        $schema = new Schema(
            discriminator: new Discriminator(
                defaultMapping: '#/components/schemas/Fallback',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Test' => $schema,
                    'Fallback' => $fallbackSchema,
                ],
            ),
        );

        $data = [
            'name' => 'Something',
            'fallbackId' => 42,
        ];

        $this->validator->validate($data, $schema, $document);

        $dataWithoutFallbackId = [
            'name' => 'Something',
        ];

        try {
            $this->validator->validate($dataWithoutFallbackId, $schema, $document);
            $this->fail('Fallback schema should require "fallbackId"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('fallbackId', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_without_property_name_and_without_default_mapping(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $data = [
            'name' => 'Something',
        ];

        $exception = null;

        try {
            $this->validator->validate($data, $schema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Validation should be skipped when discriminator has no propertyName and no defaultMapping');
    }

    #[Test]
    public function validate_with_mapping_fallback_to_default_mapping(): void
    {
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $unknownPetSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'unknownTrait'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'unknownTrait' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                ],
                defaultMapping: '#/components/schemas/UnknownPet',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'UnknownPet' => $unknownPetSchema,
                ],
            ),
        );

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'unknownTrait' => 'mysterious',
        ];

        $this->validator->validate($dogData, $petSchema, $document);

        $dogWithoutUnknownTrait = [
            'name' => 'Rex',
            'petType' => 'dog',
        ];

        try {
            $this->validator->validate($dogWithoutUnknownTrait, $petSchema, $document);
            $this->fail('UnknownPet schema selected via defaultMapping fallback should require "unknownTrait"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('unknownTrait', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_nested_data_path_builds_correct_path(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document, '/data/items');

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document, '/data/items');
            $this->fail('Cat schema should require "meow" at nested data path');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_schema_without_title_falls_through_to_unknown(): void
    {
        $schemaNoTitle = new Schema(
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/NoTitle'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'NoTitle' => $schemaNoTitle,
                ],
            ),
        );

        $this->expectException(UnknownDiscriminatorValueException::class);

        $this->validator->validate(['petType' => 'unknown'], $petSchema, $document);
    }

    #[Test]
    public function validate_with_non_string_data_throws_mismatch(): void
    {
        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => new Schema(type: 'object'),
                ],
            ),
        );

        $this->expectException(DiscriminatorMismatchException::class);

        $this->validator->validate(42, $petSchema, $document);
    }

    #[Test]
    public function validate_with_custom_logger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $validator = new DiscriminatorValidator(
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
                logger: $logger,
                eventDispatcher: $eventDispatcher,
            ),
        );

        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $validator->validate($catData, $petSchema, $document);

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema should require "meow" even with logger configured');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function validate_with_one_of_candidate_without_ref_is_skipped(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(type: 'object'),
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
            'meow' => true,
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $catWithoutMeow = [
            'name' => 'Fluffy',
            'petType' => 'Cat',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema (after skipping non-ref candidate) should require "meow"');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function cat_without_required_meow_throws_required_error(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catWithoutMeow = [
            'petType' => 'cat',
            'name' => 'Fluffy',
        ];

        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat data without required "meow" should fail validation');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function dog_without_required_bark_throws_required_error(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'breed', 'bark'],
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'breed' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $dogWithoutBark = [
            'petType' => 'dog',
            'name' => 'Rex',
            'breed' => 'Labrador',
        ];

        try {
            $this->validator->validate($dogWithoutBark, $petSchema, $document);
            $this->fail('Dog data without required "bark" should fail validation');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('bark', $errors[0]->message());
        }
    }
}
