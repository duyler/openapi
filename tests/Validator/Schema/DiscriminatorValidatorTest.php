<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DiscriminatorValidatorTest extends TestCase
{
    private DiscriminatorValidator $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->validator = new DiscriminatorValidator($this->refResolver, $this->pool);
    }

    #[Test]
    public function validate_with_mapping(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'string'),
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
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'bark' => 'loud',
        ];

        $this->validator->validate($dogData, $petSchema, $document);

        $this->assertTrue(true);
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
        $schema = new Schema(type: 'object');
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );

        $this->validator->validate(['data' => 'value'], $schema, $document);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_without_mapping_using_title(): void
    {
        $catSchema = new Schema(
            title: 'cat',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
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
        ];

        $this->validator->validate($catData, $petSchema, $document);

        $this->assertTrue(true);
    }
}
