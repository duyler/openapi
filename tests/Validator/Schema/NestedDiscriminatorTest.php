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
final class NestedDiscriminatorTest extends TestCase
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
            new InfoObject('Event API', '1.0.0'),
        );

        $this->validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);
    }

    #[Test]
    public function validate_nested_discriminator(): void
    {
        $catEventSchema = new Schema(
            title: 'CatEvent',
            type: 'object',
            properties: [
                'eventType' => new Schema(type: 'string'),
                'catName' => new Schema(type: 'string'),
            ],
        );

        $dogEventSchema = new Schema(
            title: 'DogEvent',
            type: 'object',
            properties: [
                'eventType' => new Schema(type: 'string'),
                'dogName' => new Schema(type: 'string'),
            ],
        );

        $petEventSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/CatEvent'),
                new Schema(ref: '#/components/schemas/DogEvent'),
            ],
            discriminator: new Discriminator(
                propertyName: 'eventType',
            ),
        );

        $messageSchema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string'),
                'timestamp' => new Schema(type: 'integer'),
                'event' => $petEventSchema,
            ],
            required: ['id', 'timestamp', 'event'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Event API', '1.0.0'),
            components: new Components(
                schemas: [
                    'PetEvent' => $petEventSchema,
                    'CatEvent' => $catEventSchema,
                    'DogEvent' => $dogEventSchema,
                    'Message' => $messageSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $messageData = [
            'id' => 'msg-123',
            'timestamp' => 1234567890,
            'event' => [
                'eventType' => 'CatEvent',
                'catName' => 'Fluffy',
            ],
        ];

        $validator->validate($messageData, $messageSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function handle_multiple_levels(): void
    {
        $persianCatSchema = new Schema(
            title: 'PersianCat',
            type: 'object',
            properties: [
                'breed' => new Schema(type: 'string'),
                'furLength' => new Schema(type: 'string'),
            ],
        );

        $siameseCatSchema = new Schema(
            title: 'SiameseCat',
            type: 'object',
            properties: [
                'breed' => new Schema(type: 'string'),
                'colorPoints' => new Schema(type: 'string'),
            ],
        );

        $catSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/PersianCat'),
                new Schema(ref: '#/components/schemas/SiameseCat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'breed',
            ),
        );

        $petSchema = new Schema(
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
                'details' => $catSchema,
            ],
            required: ['petType', 'details'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => $catSchema,
                    'PersianCat' => $persianCatSchema,
                    'SiameseCat' => $siameseCatSchema,
                    'Pet' => $petSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $petData = [
            'petType' => 'cat',
            'details' => [
                'breed' => 'PersianCat',
                'furLength' => 'long',
            ],
        ];

        $validator->validate($petData, $petSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function track_nested_breadcrumb(): void
    {
        $smallItemSchema = new Schema(
            title: 'SmallItem',
            type: 'object',
            properties: [
                'size' => new Schema(type: 'string'),
                'weight' => new Schema(type: 'number'),
            ],
        );

        $largeItemSchema = new Schema(
            title: 'LargeItem',
            type: 'object',
            properties: [
                'size' => new Schema(type: 'string'),
                'volume' => new Schema(type: 'number'),
            ],
        );

        $itemSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/SmallItem'),
                new Schema(ref: '#/components/schemas/LargeItem'),
            ],
            discriminator: new Discriminator(
                propertyName: 'size',
            ),
        );

        $containerSchema = new Schema(
            type: 'object',
            properties: [
                'containerId' => new Schema(type: 'string'),
                'item' => $itemSchema,
            ],
            required: ['containerId', 'item'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Container API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Item' => $itemSchema,
                    'SmallItem' => $smallItemSchema,
                    'LargeItem' => $largeItemSchema,
                    'Container' => $containerSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $containerData = [
            'containerId' => 'cont-123',
            'item' => [
                'size' => 'SmallItem',
                'weight' => 1.5,
            ],
        ];

        $validator->validate($containerData, $containerSchema);

        $this->assertTrue(true);
    }
}
