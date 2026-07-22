<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class OperationNotFoundExceptionTest extends TestCase
{
    private const string ATTACKER_REQUEST_PATH = '/<script>alert(1)</script>';

    private const string ATTACKER_METHOD = 'EVIL';

    #[Test]
    public function get_message_returns_generic_string_without_attacker_values(): void
    {
        $exception = new OperationNotFoundException(self::ATTACKER_REQUEST_PATH, self::ATTACKER_METHOD);

        self::assertSame('No operation matches the request', $exception->getMessage());
        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertStringNotContainsString('alert', $exception->getMessage());
        self::assertStringNotContainsString(self::ATTACKER_METHOD, $exception->getMessage());
        self::assertStringNotContainsString(self::ATTACKER_REQUEST_PATH, $exception->getMessage());
    }

    #[Test]
    public function to_string_is_sanitized_via_trait(): void
    {
        $exception = new OperationNotFoundException(self::ATTACKER_REQUEST_PATH, self::ATTACKER_METHOD);

        self::assertSame($exception->getMessage(), (string) $exception);
        self::assertStringNotContainsString('<script>', (string) $exception);
        self::assertStringNotContainsString(self::ATTACKER_METHOD, (string) $exception);
    }

    #[Test]
    public function get_message_is_invariant_regardless_of_attacker_input(): void
    {
        $one = new OperationNotFoundException('/a', 'GET');
        $two = new OperationNotFoundException('/b', 'POST');

        self::assertSame($one->getMessage(), $two->getMessage());
    }

    #[Test]
    public function request_path_property_preserved_for_trusted_access(): void
    {
        $exception = new OperationNotFoundException(self::ATTACKER_REQUEST_PATH, self::ATTACKER_METHOD);

        self::assertSame(self::ATTACKER_REQUEST_PATH, $exception->requestPath);
    }

    #[Test]
    public function method_property_preserved_for_trusted_access(): void
    {
        $exception = new OperationNotFoundException(self::ATTACKER_REQUEST_PATH, self::ATTACKER_METHOD);

        self::assertSame(self::ATTACKER_METHOD, $exception->method);
    }

    #[Test]
    public function propagates_code_and_previous(): void
    {
        $previous = new RuntimeException('downstream');
        $exception = new OperationNotFoundException('/users/42', 'PUT', 404, $previous);

        self::assertSame(404, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extends_runtime_exception(): void
    {
        $exception = new OperationNotFoundException('/users/42', 'PUT');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
