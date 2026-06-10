<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class DiscriminatorPropertiesTest extends TestCase
{
    private SchemaValidatorWithContext $validator;
    private ValidatorPool $pool;
    private RefResolver $refResolver;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->refResolver = new RefResolver();

        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['cat']),
                'name' => new Schema(type: 'string', minLength: 1),
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            required: ['petType', 'name', 'breed'],
            properties: [
                'petType' => new Schema(type: 'string', enum: ['dog']),
                'name' => new Schema(type: 'string', minLength: 1),
                'breed' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'owner' => new Schema(type: 'string'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
        );

        $this->document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Pet API', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $this->validator = new SchemaValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
        );
    }

    #[Test]
    public function validate_discriminator_with_valid_cat_data(): void
    {
        $data = [
            'petType' => 'cat',
            'name' => 'Fluffy',
            'owner' => 'John',
        ];

        $this->validator->validate($data, $this->resolvePetSchema());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_discriminator_with_valid_dog_data(): void
    {
        $data = [
            'petType' => 'dog',
            'name' => 'Rex',
            'breed' => 'Labrador',
            'owner' => 'Jane',
        ];

        $this->validator->validate($data, $this->resolvePetSchema());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_discriminator_rejects_missing_required_on_target(): void
    {
        $data = [
            'petType' => 'dog',
            'name' => 'Rex',
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validate($data, $this->resolvePetSchema());
    }

    #[Test]
    public function validate_discriminator_with_valid_cat_enum(): void
    {
        $data = [
            'petType' => 'cat',
            'name' => 'Fluffy',
        ];

        $petSchema = $this->resolvePetSchema();

        $this->validator->validate($data, $petSchema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_parent_properties_with_discriminator(): void
    {
        $data = [
            'petType' => 'cat',
            'name' => 'Fluffy',
            'owner' => 'John',
        ];

        $petSchema = $this->resolvePetSchema();

        $this->validator->validate($data, $petSchema);

        $this->expectNotToPerformAssertions();
    }

    private function resolvePetSchema(): Schema
    {
        return $this->refResolver->resolve('#/components/schemas/Pet', $this->document);
    }
}
