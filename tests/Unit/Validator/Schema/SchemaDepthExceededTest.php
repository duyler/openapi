<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaDepthExceededTest extends TestCase
{
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;
    private OpenApiDocument $document;
    private StatelessValidatorRegistry $statelessValidators;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());

        $recursiveSchema = new Schema(
            type: 'object',
            properties: [
                'child' => new Schema(ref: '#/components/schemas/Recursive'),
            ],
        );

        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Recursive' => $recursiveSchema,
                ],
            ),
        );
    }

    #[Test]
    public function circular_ref_throws_depth_exceeded(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Recursive');
        $validator = new SchemaValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [];
        for ($i = 0; $i <= 70; ++$i) {
            $data = ['child' => $data];
        }

        $this->expectException(SchemaDepthExceededException::class);
        $this->expectExceptionMessage('Maximum schema depth of 64 exceeded');

        $validator->validate($data, $schema);
    }

    #[Test]
    public function normal_depth_does_not_throw(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $validator = new SchemaValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));
        $data = ['name' => 'test'];

        $validator->validate($data, $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function deep_circular_ref_throws_depth_exceeded(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Recursive');
        $validator = new SchemaValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [];
        for ($i = 0; $i <= 65; ++$i) {
            $data = ['child' => $data];
        }

        $this->expectException(SchemaDepthExceededException::class);
        $validator->validate($data, $schema);
    }

    #[Test]
    public function non_recursive_nested_schema_passes(): void
    {
        $leafSchema = new Schema(type: 'string');
        $nestedSchema = new Schema(
            type: 'object',
            properties: [
                'value' => $leafSchema,
            ],
        );

        $validator = new SchemaValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));
        $data = ['value' => 'test'];

        $validator->validate($data, $nestedSchema);

        $this->assertTrue(true);
    }
}
