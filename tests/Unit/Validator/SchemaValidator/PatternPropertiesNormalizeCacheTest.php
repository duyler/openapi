<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function count;

#[CoversClass(PatternPropertiesValidator::class)]
final class PatternPropertiesNormalizeCacheTest extends TestCase
{
    private ValidatorPool $pool;

    private RegexValidator $regexValidator;

    private PatternPropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->regexValidator = new RegexValidator();
        $this->validator = new PatternPropertiesValidator(new ValidatorDependencies(
            pool: $this->pool,
            formatRegistry: BuiltinFormats::create(),
            regexValidator: $this->regexValidator,
        ));
    }

    #[Test]
    public function normalize_cache_contains_each_schema_pattern_exactly_once(): void
    {
        $stringSchema = new Schema(type: 'string');
        $intSchema = new Schema(type: 'integer');

        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^str_' => $stringSchema,
                '^num_' => $intSchema,
                '^meta_' => $stringSchema,
                '^x_' => $stringSchema,
                '^y_' => $stringSchema,
            ],
        );

        $data = [
            'str_one' => 'a',
            'str_two' => 'b',
            'num_one' => 1,
            'num_two' => 2,
            'meta_one' => 'm',
            'x_one' => 'x1',
            'y_one' => 'y1',
            'unrelated' => 'z',
            'str_three' => 'c',
            'num_three' => 3,
        ];

        $this->validator->validate($data, $schema);

        $cache = $this->normalizeCache();

        self::assertArrayHasKey('^str_', $cache);
        self::assertArrayHasKey('^num_', $cache);
        self::assertArrayHasKey('^meta_', $cache);
        self::assertArrayHasKey('^x_', $cache);
        self::assertArrayHasKey('^y_', $cache);
        self::assertCount(5, $cache);
    }

    #[Test]
    public function single_pattern_validates_many_properties(): void
    {
        $propertySchema = new Schema(type: 'string', minLength: 1);

        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^item_' => $propertySchema,
            ],
        );

        $data = [];
        foreach (range(1, 20) as $i) {
            $data['item_' . $i] = 'value' . $i;
        }

        $this->validator->validate($data, $schema);

        self::assertCount(1, $this->normalizeCache());
    }

    #[Test]
    public function repeated_validate_calls_do_not_grow_cache_for_same_schema(): void
    {
        $propertySchema = new Schema(type: 'string');

        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^a_' => $propertySchema,
                '^b_' => $propertySchema,
                '^c_' => $propertySchema,
            ],
        );

        $data = ['a_one' => 'x', 'b_one' => 'y', 'c_one' => 'z'];

        $this->validator->validate($data, $schema);
        $firstCount = count($this->normalizeCache());

        $this->validator->validate($data, $schema);
        $secondCount = count($this->normalizeCache());

        self::assertSame(3, $firstCount);
        self::assertSame(3, $secondCount);
    }

    #[Test]
    public function empty_pattern_is_skipped_in_cache(): void
    {
        $propertySchema = new Schema(type: 'string');

        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '' => $propertySchema,
                '^kept_' => $propertySchema,
            ],
        );

        $this->validator->validate(['kept_value' => 'a'], $schema);

        $cache = $this->normalizeCache();

        self::assertArrayNotHasKey('', $cache);
        self::assertArrayHasKey('^kept_', $cache);
    }

    #[Test]
    public function precompiled_patterns_match_correctness_for_overlapping_keys(): void
    {
        $stringSchema = new Schema(type: 'string', minLength: 3);
        $intSchema = new Schema(type: 'integer', minimum: 0);

        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^id_' => $intSchema,
                '^name_' => $stringSchema,
            ],
        );

        $data = [
            'id_1' => 42,
            'id_2' => 100,
            'name_first' => 'Alice',
            'name_second' => 'Bob',
        ];

        $this->validator->validate($data, $schema);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Anti-test: the precompile fix in PatternPropertiesValidator ensures
     * `RegexValidator::normalize` is invoked exactly once per distinct
     * schema pattern regardless of how many property names are matched.
     * The visible side-effect that survives the optimization is the
     * normalize-cache content: the cache contains each pattern exactly
     * once, and never contains the empty pattern.
     */
    private function normalizeCache(): array
    {
        $reflection = new ReflectionClass(RegexValidator::class);
        $property = $reflection->getProperty('normalizeCache');

        /** @var array<string, string> $cache */
        $cache = $property->getValue($this->regexValidator);

        return $cache;
    }
}
