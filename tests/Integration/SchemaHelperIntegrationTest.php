<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use DateTime;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SchemaHelperIntegrationTest extends TestCase
{
    #[Test]
    public function exception_is_thrown_when_validating_invalid_type_through_validator(): void
    {
        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Data must be array, int, string, float or bool, null given');

        SchemaValueNormalizer::normalize(null);
    }

    #[Test]
    public function valid_types_pass_through_schema_helper_in_validation_context(): void
    {
        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml');

        $validator = $builder->build();

        self::assertNotNull($validator->getDocument());

        $arrayData = ['name' => 'test'];
        $intData = 42;
        $stringData = 'test';
        $floatData = 3.14;
        $boolData = true;

        self::assertSame($arrayData, SchemaValueNormalizer::normalize($arrayData));
        self::assertSame($intData, SchemaValueNormalizer::normalize($intData));
        self::assertSame($stringData, SchemaValueNormalizer::normalize($stringData));
        self::assertSame($floatData, SchemaValueNormalizer::normalize($floatData));
        self::assertSame($boolData, SchemaValueNormalizer::normalize($boolData));
    }

    #[Test]
    public function invalid_types_in_real_scenario_throw_meaningful_exceptions(): void
    {
        $datetime = new DateTime();

        try {
            SchemaValueNormalizer::normalize($datetime);
            $this->fail('Expected InvalidDataTypeException for DateTime');
        } catch (InvalidDataTypeException $e) {
            self::assertStringContainsString('object', $e->getMessage());
        }
    }

    /**
     * P-010: stdClass is now normalized to its property-view array, matching
     * TypeCoercer::normalizeValue. Consumers using json_decode without the
     * associative flag no longer need a manual conversion step.
     */
    #[Test]
    public function stdClass_is_normalized_to_array_in_real_scenario(): void
    {
        $object = new stdClass();
        $object->name = 'Fido';
        $object->age = 3;

        $result = SchemaValueNormalizer::normalize($object);

        self::assertSame(['name' => 'Fido', 'age' => 3], $result);
    }
}
