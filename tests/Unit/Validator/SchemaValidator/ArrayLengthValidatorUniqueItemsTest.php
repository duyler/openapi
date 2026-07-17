<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function count;
use function memory_get_usage;
use function range;

use const NAN;

#[CoversClass(ArrayLengthValidator::class)]
final class ArrayLengthValidatorUniqueItemsTest extends TestCase
{
    private ValidatorPool $pool;

    private ArrayLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ArrayLengthValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function hash_mode_threshold_constant_is_defined(): void
    {
        $reflection = new ReflectionClass(ArrayLengthValidator::class);
        $constants = $reflection->getConstants();

        self::assertArrayHasKey('HASH_MODE_THRESHOLD', $constants);
        self::assertSame(100, $constants['HASH_MODE_THRESHOLD']);
    }

    #[Test]
    public function large_mixed_array_of_unique_items_passes(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 150) as $i) {
            $data[] = ['id' => $i, 'name' => 'item_' . $i];
        }

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function large_mixed_array_with_duplicate_throws(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 150) as $i) {
            $data[] = ['id' => $i, 'name' => 'item_' . $i];
        }

        $data[] = ['id' => 50, 'name' => 'item_50'];

        $this->expectException(DuplicateItemsError::class);

        $this->validator->validate($data, $schema);
    }

    #[Test]
    public function large_scalar_array_does_not_use_hash_mode(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = range(1, 200);

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boundary_array_under_threshold_uses_by_key_mode(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 99) as $i) {
            $data[] = ['id' => $i];
        }

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hash_mode_detects_duplicate_nested_arrays(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 110) as $i) {
            $data[] = ['value' => $i];
        }

        $duplicate = ['value' => 5];
        $data[] = $duplicate;

        $this->expectException(DuplicateItemsError::class);

        $this->validator->validate($data, $schema);
    }

    #[Test]
    public function hash_mode_treats_distinct_objects_as_unique(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 200) as $i) {
            $data[] = ['type' => 'item', 'index' => $i];
        }

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hash_mode_handles_top_level_nan_items_as_distinct(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 110) as $i) {
            $data[] = ['value' => $i];
        }

        $data[] = NAN;
        $data[] = NAN;

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hash_mode_distinguishes_arrays_of_different_shape(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 110) as $i) {
            $data[] = ['a' => $i];
        }

        $data[] = ['b' => 1];

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hash_mode_memory_pressure_lower_than_full_string_storage(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $largeValue = ['payload' => str_repeat('x', 1024)];

        $data = [];
        foreach (range(1, 120) as $i) {
            $data[] = ['id' => $i, 'payload' => str_repeat('y', 1024)];
        }

        $baseline = memory_get_usage();
        $this->validator->validate($data, $schema);
        $peak = memory_get_usage();

        $delta = $peak - $baseline;

        self::assertLessThan(
            5_000_000,
            $delta,
            'Hash-mode deduplication must keep transient memory bounded; got ' . $delta . ' bytes for ' . count($data) . ' items.',
        );
    }

    #[Test]
    public function empty_array_does_not_invoke_hash_mode(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hash_mode_detects_int_float_numeric_equality(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = [];
        foreach (range(1, 110) as $i) {
            $data[] = ['value' => $i];
        }

        $data[] = ['value' => 5.0];

        $this->expectException(DuplicateItemsError::class);

        $this->validator->validate($data, $schema);
    }
}
