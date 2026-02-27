<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DiscriminatorIntegrationTest extends TestCase
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
            new InfoObject('API', '1.0.0'),
        );

        $this->validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);
    }

    #[Test]
    public function validate_real_world_polymorphic_schema(): void
    {
        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['petType', 'name', 'age'],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'breed' => new Schema(type: 'string'),
            ],
            required: ['petType', 'name', 'age', 'breed'],
        );

        $birdSchema = new Schema(
            title: 'Bird',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'canFly' => new Schema(type: 'boolean'),
            ],
            required: ['petType', 'name', 'canFly'],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
                new Schema(ref: '#/components/schemas/Bird'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                    'bird' => '#/components/schemas/Bird',
                ],
            ),
        );

        $petStoreSchema = new Schema(
            type: 'object',
            properties: [
                'storeId' => new Schema(type: 'string'),
                'pets' => new Schema(
                    type: 'array',
                    items: $petSchema,
                ),
            ],
            required: ['storeId', 'pets'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet Store API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                    'Bird' => $birdSchema,
                    'PetStore' => $petStoreSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $petStoreData = [
            'storeId' => 'store-123',
            'pets' => [
                [
                    'petType' => 'cat',
                    'name' => 'Fluffy',
                    'age' => 3,
                ],
                [
                    'petType' => 'dog',
                    'name' => 'Rex',
                    'age' => 5,
                    'breed' => 'German Shepherd',
                ],
                [
                    'petType' => 'bird',
                    'name' => 'Tweety',
                    'canFly' => true,
                ],
            ],
        ];

        $validator->validate($petStoreData, $petStoreSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function work_with_all_of(): void
    {
        $baseSchema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string'),
                'createdAt' => new Schema(type: 'string'),
            ],
            required: ['id', 'createdAt'],
        );

        $catSchema = new Schema(
            title: 'Cat',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
            required: ['petType', 'name'],
        );

        $catWithBaseSchema = new Schema(
            title: 'Cat',
            allOf: [
                $baseSchema,
                $catSchema,
            ],
        );

        $dogSchema = new Schema(
            title: 'Dog',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
            required: ['petType', 'name'],
        );

        $dogWithBaseSchema = new Schema(
            title: 'Dog',
            allOf: [
                $baseSchema,
                $dogSchema,
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/CatWithBase'),
                new Schema(ref: '#/components/schemas/DogWithBase'),
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
                    'Base' => $baseSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                    'CatWithBase' => $catWithBaseSchema,
                    'DogWithBase' => $dogWithBaseSchema,
                    'Pet' => $petSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $catData = [
            'id' => 'cat-123',
            'createdAt' => '2024-01-01T00:00:00Z',
            'petType' => 'Cat',
            'name' => 'Fluffy',
        ];

        $validator->validate($catData, $petSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function handle_circular_refs(): void
    {
        $nodeSchema = new Schema(
            title: 'Node',
            type: 'object',
            properties: [
                'value' => new Schema(type: 'string'),
                'next' => new Schema(ref: '#/components/schemas/Node'),
            ],
        );

        $listNodeSchema = new Schema(
            title: 'ListNode',
            type: 'object',
            properties: [
                'nodeType' => new Schema(type: 'string'),
                'head' => new Schema(ref: '#/components/schemas/Node'),
            ],
        );

        $treeNodeSchema = new Schema(
            title: 'TreeNode',
            type: 'object',
            properties: [
                'nodeType' => new Schema(type: 'string'),
                'left' => new Schema(ref: '#/components/schemas/Node'),
                'right' => new Schema(ref: '#/components/schemas/Node'),
            ],
        );

        $nodeSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/ListNode'),
                new Schema(ref: '#/components/schemas/TreeNode'),
            ],
            discriminator: new Discriminator(
                propertyName: 'nodeType',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Node API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Node' => $nodeSchema,
                    'ListNode' => $listNodeSchema,
                    'TreeNode' => $treeNodeSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $treeData = [
            'nodeType' => 'TreeNode',
            'left' => [
                'nodeType' => 'ListNode',
                'head' => [
                    'value' => 'leaf1',
                ],
            ],
            'right' => [
                'nodeType' => 'ListNode',
                'head' => [
                    'value' => 'leaf2',
                ],
            ],
        ];

        $validator->validate($treeData, $nodeSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_complex_api_response(): void
    {
        $successResponseSchema = new Schema(
            title: 'Success',
            type: 'object',
            properties: [
                'status' => new Schema(type: 'string'),
                'data' => new Schema(
                    type: 'object',
                    properties: [
                        'result' => new Schema(type: 'string'),
                    ],
                ),
            ],
            required: ['status', 'data'],
        );

        $errorResponseSchema = new Schema(
            title: 'Error',
            type: 'object',
            properties: [
                'status' => new Schema(type: 'string'),
                'error' => new Schema(
                    type: 'object',
                    properties: [
                        'code' => new Schema(type: 'string'),
                        'message' => new Schema(type: 'string'),
                    ],
                ),
            ],
            required: ['status', 'error'],
        );

        $responseSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Success'),
                new Schema(ref: '#/components/schemas/Error'),
            ],
            discriminator: new Discriminator(
                propertyName: 'status',
                mapping: [
                    'success' => '#/components/schemas/Success',
                    'error' => '#/components/schemas/Error',
                ],
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Response' => $responseSchema,
                    'Success' => $successResponseSchema,
                    'Error' => $errorResponseSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $successData = [
            'status' => 'success',
            'data' => [
                'result' => 'Operation completed',
            ],
        ];

        $validator->validate($successData, $responseSchema);

        $errorData = [
            'status' => 'error',
            'error' => [
                'code' => 'ERR_001',
                'message' => 'Invalid input',
            ],
        ];

        $validator->validate($errorData, $responseSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function throw_error_for_invalid_discriminator_value_in_nested_structure(): void
    {
        $this->expectException(UnknownDiscriminatorValueException::class);

        $smallSchema = new Schema(
            title: 'Small',
            type: 'object',
            properties: [
                'size' => new Schema(type: 'string'),
            ],
        );

        $largeSchema = new Schema(
            title: 'Large',
            type: 'object',
            properties: [
                'size' => new Schema(type: 'string'),
            ],
        );

        $itemSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Small'),
                new Schema(ref: '#/components/schemas/Large'),
            ],
            discriminator: new Discriminator(
                propertyName: 'size',
            ),
        );

        $containerSchema = new Schema(
            type: 'object',
            properties: [
                'item' => $itemSchema,
            ],
            required: ['item'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Container API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Item' => $itemSchema,
                    'Small' => $smallSchema,
                    'Large' => $largeSchema,
                    'Container' => $containerSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $containerData = [
            'item' => [
                'size' => 'Medium',
            ],
        ];

        $validator->validate($containerData, $containerSchema);
    }
}
