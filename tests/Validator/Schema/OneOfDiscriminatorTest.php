<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class OneOfDiscriminatorTest extends TestCase
{
    private SchemaValidatorWithContext $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
        );

        $this->validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);
    }

    #[Test]
    public function use_discriminator_for_schema_selection(): void
    {
        $catSchema = new Schema(
            title: 'cat',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            title: 'dog',
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

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $catData = [
            'name' => 'Fluffy',
            'petType' => 'cat',
        ];

        $validator->validate($catData, $petSchema);

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'bark' => 'loud',
        ];

        $validator->validate($dogData, $petSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function fallback_to_oneof_when_no_discriminator(): void
    {
        $stringSchema = new Schema(type: 'string');
        $numberSchema = new Schema(type: 'number');

        $valueSchema = new Schema(
            oneOf: [
                $stringSchema,
                $numberSchema,
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Value API', '1.0.0'),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $validator->validate('test', $valueSchema);
        $validator->validate(42, $valueSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_correctly_with_discriminator(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
            ],
            required: ['name', 'petType'],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'petType' => new Schema(type: 'string'),
                'bark' => new Schema(type: 'string'),
            ],
            required: ['name', 'petType', 'bark'],
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

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $dogData = [
            'name' => 'Rex',
            'petType' => 'dog',
            'bark' => 'loud',
        ];

        $validator->validate($dogData, $petSchema);

        $this->assertTrue(true);
    }
}
