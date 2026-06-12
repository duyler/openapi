<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorDepthLimitTest extends TestCase
{
    private SchemaValidator $validator;
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new SchemaValidator(
            pool: $this->pool,
            formatRegistry: BuiltinFormats::create(),
        );
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

        $this->validator->validate(['name' => 'test'], $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function deeply_nested_schema_throws_depth_exceeded(): void
    {
        $leafSchema = new Schema(type: 'string');
        $current = $leafSchema;
        for ($i = 0; $i < 70; ++$i) {
            $current = new Schema(
                type: 'object',
                properties: ['nested' => $current],
            );
        }

        $data = [];
        for ($i = 0; $i < 70; ++$i) {
            $data = ['nested' => $data];
        }

        $this->expectException(SchemaDepthExceededException::class);
        $this->expectExceptionMessage('Maximum schema depth of 64 exceeded');

        $this->validator->validate($data, $current);
    }

    #[Test]
    public function nested_array_items_throws_depth_exceeded(): void
    {
        $leafSchema = new Schema(type: 'string');
        $current = $leafSchema;
        for ($i = 0; $i < 70; ++$i) {
            $current = new Schema(
                type: 'array',
                items: $current,
            );
        }

        $data = [];
        for ($i = 0; $i < 70; ++$i) {
            $data = [$data];
        }

        $this->expectException(SchemaDepthExceededException::class);

        $this->validator->validate($data, $current);
    }
}
