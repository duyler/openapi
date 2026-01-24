<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\ConstError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConstErrorTest extends TestCase
{
    #[Test]
    public function keyword_returns_const(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('const', $exception->keyword());
    }

    #[Test]
    public function dataPath_returns_correct_value(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('/field', $exception->dataPath());
    }

    #[Test]
    public function schemaPath_returns_correct_value(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('/properties/field', $exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $expectedMessage = 'Value ""different-value"" does not match const value ""test-value"" at /field';
        self::assertSame($expectedMessage, $exception->message());
    }

    #[Test]
    public function params_returns_const_value(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();

        self::assertIsArray($params);
        self::assertArrayHasKey('expected', $params);
        self::assertArrayHasKey('actual', $params);
        self::assertSame('test-value', $params['expected']);
        self::assertSame('different-value', $params['actual']);
    }

    #[Test]
    public function suggestion_returns_correct_value(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('Use const value: "test-value"', $exception->suggestion());
    }

    #[Test]
    public function getType_returns_const(): void
    {
        $exception = new ConstError(
            expected: 'test-value',
            actual: 'different-value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        self::assertSame('const', $exception->getType());
    }

    #[Test]
    public function handles_object_values(): void
    {
        $expected = ['name' => 'test'];
        $actual = ['name' => 'different'];

        $exception = new ConstError(
            expected: $expected,
            actual: $actual,
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();
        self::assertSame($expected, $params['expected']);
        self::assertSame($actual, $params['actual']);
    }

    #[Test]
    public function handles_null_values(): void
    {
        $exception = new ConstError(
            expected: null,
            actual: 'value',
            dataPath: '/field',
            schemaPath: '/properties/field',
        );

        $params = $exception->params();
        self::assertNull($params['expected']);
        self::assertSame('value', $params['actual']);
    }
}
