<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class PathMismatchExceptionTest extends TestCase
{
    private const string ATTACKER_REQUEST_PATH = '/<script>alert(1)</script>';

    #[Test]
    public function get_message_returns_generic_string_without_attacker_path(): void
    {
        $exception = new PathMismatchException('/users/{id}', self::ATTACKER_REQUEST_PATH);

        self::assertSame('Request path does not match any declared template', $exception->getMessage());
        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertStringNotContainsString('alert', $exception->getMessage());
        self::assertStringNotContainsString(self::ATTACKER_REQUEST_PATH, $exception->getMessage());
    }

    #[Test]
    public function to_string_is_sanitized_via_trait(): void
    {
        $exception = new PathMismatchException('/users/{id}', self::ATTACKER_REQUEST_PATH);

        self::assertSame($exception->getMessage(), (string) $exception);
        self::assertStringNotContainsString('<script>', (string) $exception);
        self::assertStringNotContainsString(self::ATTACKER_REQUEST_PATH, (string) $exception);
    }

    #[Test]
    public function get_message_is_invariant_regardless_of_attacker_input(): void
    {
        $one = new PathMismatchException('/users/{id}', '/attacker-a');
        $two = new PathMismatchException('/users/{id}', '/attacker-b');

        self::assertSame($one->getMessage(), $two->getMessage());
    }

    #[Test]
    public function request_path_property_preserved_for_trusted_access(): void
    {
        $exception = new PathMismatchException('/users/{id}', self::ATTACKER_REQUEST_PATH);

        self::assertSame(self::ATTACKER_REQUEST_PATH, $exception->requestPath);
    }

    #[Test]
    public function template_property_preserved_for_trusted_access(): void
    {
        $exception = new PathMismatchException('/users/{id}', self::ATTACKER_REQUEST_PATH);

        self::assertSame('/users/{id}', $exception->template);
    }

    #[Test]
    public function propagates_code_and_previous(): void
    {
        $previous = new RuntimeException('downstream');
        $exception = new PathMismatchException('/users/{id}', '/path', 418, $previous);

        self::assertSame(418, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extends_runtime_exception(): void
    {
        $exception = new PathMismatchException('/users/{id}', '/users/42');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
