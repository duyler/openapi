<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\EnumScalarCache;
use Duyler\OpenApi\Validator\SchemaValidator\EnumValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function range;
use function sprintf;

use const NAN;

#[CoversClass(EnumValidator::class)]
final class EnumValidatorHashSetTest extends TestCase
{
    private ValidatorPool $pool;

    private EnumValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new EnumValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function scalar_enum_validates_string_in_set(): void
    {
        $enum = ['red', 'green', 'blue'];

        $schema = new Schema(type: 'string', enum: $enum);

        $this->validator->validate('red', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function scalar_enum_rejects_missing_string(): void
    {
        $enum = ['red', 'green', 'blue'];

        $schema = new Schema(type: 'string', enum: $enum);

        $this->expectException(EnumError::class);

        $this->validator->validate('yellow', $schema);
    }

    #[Test]
    public function scalar_enum_handles_large_value_set_without_linear_scan(): void
    {
        $enum = [];
        foreach (range(1, 50) as $i) {
            $enum[] = sprintf('value_%d', $i);
        }

        $schema = new Schema(type: 'string', enum: $enum);

        $this->validator->validate('value_50', $schema);
        $this->validator->validate('value_1', $schema);

        $this->expectException(EnumError::class);
        $this->validator->validate('value_51_does_not_exist', $schema);
    }

    #[Test]
    public function scalar_enum_distinguishes_int_and_string_key(): void
    {
        $schema = new Schema(enum: [1, 2, 3]);

        $this->expectException(EnumError::class);
        $this->validator->validate('1', $schema);
    }

    #[Test]
    public function scalar_enum_treats_int_and_float_as_equal(): void
    {
        $schema = new Schema(enum: [1, 2, 3]);

        $this->validator->validate(1.0, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function scalar_enum_distinguishes_bool_and_int(): void
    {
        $schema = new Schema(enum: [1, 0]);

        $this->expectException(EnumError::class);
        $this->validator->validate(true, $schema);
    }

    #[Test]
    public function scalar_enum_distinguishes_true_and_false(): void
    {
        $schema = new Schema(enum: [true]);

        $this->expectException(EnumError::class);
        $this->validator->validate(false, $schema);
    }

    #[Test]
    public function scalar_enum_accepts_null_when_null_is_in_enum(): void
    {
        $schema = new Schema(enum: ['value', null]);

        $this->validator->validate(null, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function scalar_enum_rejects_nan_data_against_numeric_enum(): void
    {
        $schema = new Schema(enum: [1.0, 2.0, 3.0]);

        $this->expectException(EnumError::class);
        $this->validator->validate(NAN, $schema);
    }

    #[Test]
    public function mixed_enum_falls_back_to_json_equals_for_array_value(): void
    {
        $schema = new Schema(enum: [['a' => 1], ['a' => 2]]);

        $this->validator->validate(['a' => 1], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function mixed_enum_rejects_non_matching_array(): void
    {
        $schema = new Schema(enum: [['a' => 1], ['a' => 2]]);

        $this->expectException(EnumError::class);
        $this->validator->validate(['a' => 3], $schema);
    }

    #[Test]
    public function scalar_enum_caches_set_per_schema_instance(): void
    {
        $enum = ['red', 'green', 'blue'];

        $schema = new Schema(type: 'string', enum: $enum);

        $this->validator->validate('red', $schema);
        $this->validator->validate('green', $schema);
        $this->validator->validate('blue', $schema);

        $cache = $this->extractScalarCache();

        self::assertTrue($cache->isScalarLookupEligible($schema, 'red'));
        self::assertTrue($cache->isScalarLookupEligible($schema, 'green'));
        self::assertTrue($cache->isScalarLookupEligible($schema, 'blue'));
        self::assertTrue($cache->contains($schema, 'red'));
        self::assertTrue($cache->contains($schema, 'green'));
        self::assertTrue($cache->contains($schema, 'blue'));
        self::assertFalse($cache->contains($schema, 'yellow'));
    }

    #[Test]
    public function mixed_enum_does_not_populate_scalar_set_cache(): void
    {
        $schema = new Schema(enum: ['string', 42, true, null, ['nested' => 'array']]);

        $this->validator->validate('string', $schema);

        $cache = $this->extractScalarCache();

        self::assertFalse($cache->isScalarLookupEligible($schema, 'string'));
        self::assertFalse($cache->isScalarLookupEligible($schema, ['nested' => 'array']));
    }

    #[Test]
    public function is_scalar_enum_cache_marks_mixed_enum(): void
    {
        $scalarSchema = new Schema(enum: ['a', 'b']);
        $mixedSchema = new Schema(enum: ['a', ['nested' => 'value']]);

        $this->validator->validate('a', $scalarSchema);
        $this->validator->validate('a', $mixedSchema);

        $cache = $this->extractScalarCache();

        self::assertTrue($cache->isScalarLookupEligible($scalarSchema, 'a'));
        self::assertFalse($cache->isScalarLookupEligible($mixedSchema, 'a'));
    }

    #[Test]
    public function scalar_enum_rejects_non_scalar_data_immediately(): void
    {
        $schema = new Schema(enum: ['a', 'b', 'c']);

        $this->expectException(EnumError::class);
        $this->validator->validate(['a'], $schema);
    }

    private function extractScalarCache(): EnumScalarCache
    {
        $reflection = new ReflectionClass(EnumValidator::class);
        $property = $reflection->getProperty('scalarCache');

        /** @var EnumScalarCache $cache */
        $cache = $property->getValue($this->validator);

        return $cache;
    }
}
