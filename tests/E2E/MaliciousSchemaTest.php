<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function microtime;

final class MaliciousSchemaTest extends TestCase
{
    private const int ENUM_VALUE_COUNT = 100000;

    private const string EXISTING_ENUM_VALUE = 'value_50000';

    private const float MAX_VALIDATION_SECONDS = 0.5;

    private const int SPEC_SIZE_CAP = 5_242_880;

    private string $specWithHugeEnum;

    #[Override]
    protected function setUp(): void
    {
        $enumJson = '';

        for ($i = 1; $i <= self::ENUM_VALUE_COUNT; ++$i) {
            if (1 !== $i) {
                $enumJson .= ',';
            }

            $enumJson .= '"value_' . $i . '"';
        }

        $this->specWithHugeEnum = '{"openapi":"3.2.0","info":{"title":"Malicious Schema Test API","version":"1.0.0"},"paths":{},"components":{"schemas":{"Choice":{"type":"string","enum":[' . $enumJson . ']}}}}';
    }

    #[Test]
    public function huge_enum_value_present_validates_within_reasonable_time(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithHugeEnum)
            ->withMaxSpecSize(self::SPEC_SIZE_CAP)
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
            ->withMaxSpecSize(self::SPEC_SIZE_CAP)
            ->build();

        $startTime = microtime(true);

        try {
            $validator->validateSchema('not_in_enum', '#/components/schemas/Choice');
            $this->fail('Expected ValidationException containing EnumError for value not in huge enum');
        } catch (ValidationException $exception) {
            $error = $exception->getErrors()[0] ?? null;
            $elapsed = microtime(true) - $startTime;

            $this->assertInstanceOf(EnumError::class, $error);
            $this->assertSame('enum', $error->keyword());
            $this->assertSame('not_in_enum', $error->params()['actual']);
            $this->assertLessThan(self::MAX_VALIDATION_SECONDS, $elapsed, 'Huge enum negative validation must complete within reasonable time');
        }
    }
}
