<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\EnumError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnumErrorTest extends TestCase
{
    #[Test]
    public function keyword_returns_enum(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        self::assertSame('enum', $exception->keyword());
    }

    #[Test]
    public function dataPath_returns_correct_value(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        self::assertSame('/color', $exception->dataPath());
    }

    #[Test]
    public function schemaPath_returns_correct_value(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        self::assertSame('/properties/color', $exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        $expectedMessage = 'Value ""yellow"" is not in allowed values: ["red","green","blue"] at /color';
        self::assertSame($expectedMessage, $exception->message());
    }

    #[Test]
    public function params_returns_enum_value_and_values(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        $params = $exception->params();

        self::assertIsArray($params);
        self::assertArrayHasKey('allowed', $params);
        self::assertArrayHasKey('actual', $params);
        self::assertSame(['red', 'green', 'blue'], $params['allowed']);
        self::assertSame('yellow', $params['actual']);
    }

    #[Test]
    public function suggestion_returns_correct_value(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        self::assertSame('Use one of the allowed values: red, green, blue', $exception->suggestion());
    }

    #[Test]
    public function getType_returns_enum(): void
    {
        $exception = new EnumError(
            allowedValues: ['red', 'green', 'blue'],
            actual: 'yellow',
            dataPath: '/color',
            schemaPath: '/properties/color',
        );

        self::assertSame('enum', $exception->getType());
    }

    #[Test]
    public function handles_numeric_values(): void
    {
        $exception = new EnumError(
            allowedValues: [1, 2, 3],
            actual: 4,
            dataPath: '/number',
            schemaPath: '/properties/number',
        );

        $params = $exception->params();
        self::assertSame([1, 2, 3], $params['allowed']);
        self::assertSame(4, $params['actual']);
        self::assertSame('Use one of the allowed values: 1, 2, 3', $exception->suggestion());
    }

    #[Test]
    public function handles_mixed_values(): void
    {
        $exception = new EnumError(
            allowedValues: ['string', 123, true, null],
            actual: 'other',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();
        self::assertSame(['string', 123, true, null], $params['allowed']);
        self::assertSame('other', $params['actual']);
    }

    #[Test]
    public function handles_object_values_in_allowed(): void
    {
        $exception = new EnumError(
            allowedValues: [['id' => 1], ['id' => 2]],
            actual: ['id' => 3],
            dataPath: '/item',
            schemaPath: '/properties/item',
        );

        $params = $exception->params();
        self::assertSame([['id' => 1], ['id' => 2]], $params['allowed']);
        self::assertSame(['id' => 3], $params['actual']);
    }
}
