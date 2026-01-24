<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractValidationErrorTest extends TestCase
{
    #[Test]
    public function keyword_is_correct_for_const_subclass(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('const', $exception->keyword());
    }

    #[Test]
    public function keyword_is_correct_for_enum_subclass(): void
    {
        $exception = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('enum', $exception->keyword());
    }

    #[Test]
    public function dataPath_returns_correct_value_for_string(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/users/0/name',
            schemaPath: '/properties/field',
        );

        self::assertSame('/users/0/name', $exception->dataPath());
    }

    #[Test]
    public function dataPath_returns_correct_value_for_nested(): void
    {
        $exception = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/data/items/0',
            schemaPath: '/properties/items',
        );

        self::assertSame('/data/items/0', $exception->dataPath());
    }

    #[Test]
    public function schemaPath_returns_correct_value(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/users/items/0',
        );

        self::assertSame('/properties/users/items/0', $exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $expectedMessage = 'Value ""different"" does not match const value ""test"" at /field';
        self::assertSame($expectedMessage, $exception->message());
    }

    #[Test]
    public function params_returns_correct_value_for_const(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();
        self::assertSame(['expected' => 'test', 'actual' => 'different'], $params);
    }

    #[Test]
    public function params_returns_correct_value_for_enum(): void
    {
        $exception = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();
        self::assertSame(['allowed' => ['a', 'b'], 'actual' => 'c'], $params);
    }

    #[Test]
    public function suggestion_returns_correct_value_for_const(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('Use const value: "test"', $exception->suggestion());
    }

    #[Test]
    public function suggestion_returns_correct_value_for_enum(): void
    {
        $exception = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('Use one of the allowed values: a, b', $exception->suggestion());
    }

    #[Test]
    public function getType_returns_correct_value_for_const(): void
    {
        $exception = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('const', $exception->getType());
    }

    #[Test]
    public function getType_returns_correct_value_for_enum(): void
    {
        $exception = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('enum', $exception->getType());
    }

    #[Test]
    public function getType_matches_keyword_for_all_subclasses(): void
    {
        $constException = new ConstError(
            expected: 'test',
            actual: 'different',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $enumException = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame($constException->keyword(), $constException->getType());
        self::assertSame($enumException->keyword(), $enumException->getType());
    }
}
