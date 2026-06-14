<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function array_map;
use function json_encode;
use function microtime;
use function range;

use const JSON_THROW_ON_ERROR;

final class MaliciousSchemaTest extends TestCase
{
    private const ENUM_VALUE_COUNT = 100000;

    private const EXISTING_ENUM_VALUE = 'value_50000';

    private const MAX_VALIDATION_SECONDS = 0.5;

    private string $specWithHugeEnum;

    #[Override]
    protected function setUp(): void
    {
        $enumValues = array_map(
            static fn(int $value) => "value_{$value}",
            range(1, self::ENUM_VALUE_COUNT),
        );

        $this->specWithHugeEnum = json_encode([
            'openapi' => '3.2.0',
            'info' => [
                'title' => 'Malicious Schema Test API',
                'version' => '1.0.0',
            ],
            'paths' => (object) [],
            'components' => [
                'schemas' => [
                    'Choice' => [
                        'type' => 'string',
                        'enum' => $enumValues,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function huge_enum_value_present_validates_within_reasonable_time(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithHugeEnum)
            ->build();

        $startTime = microtime(true);

        $validator->validateSchema(self::EXISTING_ENUM_VALUE, '#/components/schemas/Choice');

        $elapsed = microtime(true) - $startTime;

        $this->assertLessThan(self::MAX_VALIDATION_SECONDS, $elapsed, 'Huge enum validation must complete within reasonable time');
    }

    #[Test]
    public function huge_enum_value_absent_throws_enum_error_within_reasonable_time(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithHugeEnum)
            ->build();

        $startTime = microtime(true);

        try {
            $validator->validateSchema('not_in_enum', '#/components/schemas/Choice');
            $this->fail('Expected EnumError for value not in huge enum');
        } catch (EnumError $error) {
            $elapsed = microtime(true) - $startTime;

            $this->assertSame('enum', $error->keyword());
            $this->assertSame('not_in_enum', $error->params()['actual']);
            $this->assertLessThan(self::MAX_VALIDATION_SECONDS, $elapsed, 'Huge enum negative validation must complete within reasonable time');
        }
    }
}
