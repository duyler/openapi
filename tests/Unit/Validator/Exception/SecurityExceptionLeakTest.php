<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function sort;
use function str_replace;
use function strlen;
use function substr;
use function sprintf;

use function dirname;

use const DIRECTORY_SEPARATOR;

/**
 * Regression coverage for R3-SEC-INFO-LEAK-SYSTEMATIC (CWE-209 / CWE-497):
 * every exception class shipped by the package MUST override __toString()
 * (either directly or via SanitizableExceptionTrait) so the default
 * Exception::__toString() cannot leak server filesystem paths, class
 * names, or stack traces through (string) $e casts in PSR-15 middleware
 * or PSR-3 loggers.
 *
 * Sensitive public readonly properties on category-B exception classes
 * are also verified to be inaccessible directly (they require explicit
 * opt-in getters with $reveal = true).
 *
 * @internal
 */
final class SecurityExceptionLeakTest extends TestCase
{
    /**
     * Discovers every *Exception.php and *Error.php file under src/ and
     * resolves its FQCN via the file path. This guarantees that every
     * present and future exception class is covered; new classes cannot
     * silently bypass the sanitisation contract.
     *
     * @return array<string, array{class-string<Throwable>}>
     */
    public static function provideExceptionClasses(): array
    {
        $srcDir = dirname(__DIR__, 4) . '/src';
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $basename = $file->getFilename();
            if (!str_ends_with($basename, 'Exception.php') && !str_ends_with($basename, 'Error.php')) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        $fixture = [];
        foreach ($files as $file) {
            $relativePath = substr($file, strlen($srcDir) + 1);
            $relativePath = str_replace(['.php', DIRECTORY_SEPARATOR], ['', '\\'], $relativePath);
            $class = 'Duyler\\OpenApi\\' . $relativePath;

            if (!class_exists($class) && !interface_exists($class)) {
                continue;
            }

            $fixture[$class] = [$class];
        }

        return $fixture;
    }

    /**
     * @param class-string<Throwable> $class
     */
    #[Test]
    #[DataProvider('provideExceptionClasses')]
    public function all_exception_classes_in_fixture_list_sanitize_to_string_or_have_no_sensitive_public_props(string $class): void
    {
        $reflection = new ReflectionClass($class);

        $hasTraitOverride = self::classUsesSanitizableTrait($reflection);
        $hasDirectOverride = $reflection->hasMethod('__toString')
            && $reflection->getMethod('__toString')->getDeclaringClass()->getName() === $class;

        self::assertTrue(
            $hasTraitOverride || $hasDirectOverride,
            sprintf(
                '%s must override __toString() (directly or via SanitizableExceptionTrait) '
                . 'to prevent CWE-209 filesystem / stack-trace leak through (string) $e casts.',
                $class,
            ),
        );
    }

    #[Test]
    public function external_ref_security_exception_does_not_leak_absolute_path_via_to_string(): void
    {
        $sensitive = '/var/www/app/config/secrets.yaml';

        $exception = new ExternalRefSecurityException($sensitive, 'denied');

        self::assertStringNotContainsString($sensitive, (string) $exception);
    }

    #[Test]
    public function external_ref_security_exception_ref_property_is_protected(): void
    {
        $exception = new ExternalRefSecurityException('/var/www/app/config/secrets.yaml', 'denied');

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->ref;
    }

    #[Test]
    public function external_ref_security_exception_ref_getter_returns_redacted_by_default(): void
    {
        $exception = new ExternalRefSecurityException('/var/www/app/config/secrets.yaml', 'denied');

        self::assertSame('<redacted>', $exception->ref());
    }

    #[Test]
    public function external_ref_security_exception_ref_getter_returns_value_with_reveal(): void
    {
        $exception = new ExternalRefSecurityException('config/secrets.yaml', 'denied');

        self::assertSame('config/secrets.yaml', $exception->ref(reveal: true));
    }

    #[Test]
    public function unresolvable_ref_exception_internal_trace_is_protected(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Missing',
            reason: 'not found',
            internalTrace: '#/components/schemas/A -> #/components/schemas/Missing',
        );

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->internalTrace;
    }

    #[Test]
    public function unresolvable_ref_exception_internal_trace_getter_returns_redacted_by_default(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Missing',
            reason: 'not found',
            internalTrace: '#/components/schemas/A -> #/components/schemas/Missing',
        );

        self::assertSame('<redacted>', $exception->internalTrace());
    }

    #[Test]
    public function unresolvable_ref_exception_internal_trace_getter_returns_value_with_reveal(): void
    {
        $exception = new UnresolvableRefException(
            ref: '#/components/schemas/Missing',
            reason: 'not found',
            internalTrace: '#/components/schemas/A -> #/components/schemas/Missing',
        );

        self::assertSame(
            '#/components/schemas/A -> #/components/schemas/Missing',
            $exception->internalTrace(reveal: true),
        );
    }

    #[Test]
    public function unresolvable_ref_exception_ref_is_protected(): void
    {
        $exception = new UnresolvableRefException('file:///etc/passwd', 'denied');

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->ref;
    }

    #[Test]
    public function invalid_format_exception_value_is_protected(): void
    {
        $exception = new InvalidFormatException('uuid', 'super-secret-token', 'Invalid');

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->value;
    }

    #[Test]
    public function invalid_format_exception_value_getter_returns_redacted_by_default(): void
    {
        $exception = new InvalidFormatException('uuid', 'super-secret-token', 'Invalid');

        self::assertSame('<redacted>', $exception->value());
    }

    #[Test]
    public function invalid_format_exception_value_getter_returns_value_with_reveal(): void
    {
        $exception = new InvalidFormatException('uuid', 'super-secret-token', 'Invalid');

        self::assertSame('super-secret-token', $exception->value(reveal: true));
    }

    #[Test]
    public function missing_security_credentials_scheme_details_are_protected(): void
    {
        $exception = new MissingSecurityCredentialsError(
            schemeName: 'bearerAuth',
            schemeType: 'http/bearer',
            location: 'Authorization header',
        );

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->schemeName;
    }

    #[Test]
    public function missing_security_credentials_getters_return_redacted_by_default(): void
    {
        $exception = new MissingSecurityCredentialsError(
            schemeName: 'bearerAuth',
            schemeType: 'http/bearer',
            location: 'Authorization header',
        );

        self::assertSame('<redacted>', $exception->schemeName());
        self::assertSame('<redacted>', $exception->schemeType());
        self::assertSame('<redacted>', $exception->location());
    }

    #[Test]
    public function missing_security_credentials_getters_return_values_with_reveal(): void
    {
        $exception = new MissingSecurityCredentialsError(
            schemeName: 'bearerAuth',
            schemeType: 'http/bearer',
            location: 'Authorization header',
        );

        self::assertSame('bearerAuth', $exception->schemeName(reveal: true));
        self::assertSame('http/bearer', $exception->schemeType(reveal: true));
        self::assertSame('Authorization header', $exception->location(reveal: true));
    }

    #[Test]
    public function malformed_stream_record_exception_truncates_attacker_record(): void
    {
        $attackerRecord = str_repeat('x', 1024 * 1024);
        $previous = new RuntimeException('json decode failed');

        $exception = new MalformedStreamRecordException($attackerRecord, $previous);

        self::assertLessThanOrEqual(
            512,
            strlen($exception->record),
            'Retained $record must be bounded (truncated + suffix).',
        );
    }

    #[Test]
    public function malformed_stream_record_exception_escapes_control_chars_in_record(): void
    {
        $previous = new RuntimeException('json decode failed');
        $exception = new MalformedStreamRecordException("line1\nline2\x00binary", $previous);

        self::assertStringContainsString('\x0a', $exception->record);
        self::assertStringContainsString('\x00', $exception->record);
    }

    #[Test]
    public function invalid_parameter_exception_parameter_name_is_protected(): void
    {
        $exception = new InvalidParameterException('attacker_key', 'reason');

        self::expectException(Error::class);
        self::expectExceptionMessage('Cannot access protected property');

        /** @phpstan-ignore-next-line intentional access to verify protected visibility */
        $exception->parameterName;
    }

    #[Test]
    public function invalid_parameter_exception_getters_return_redacted_by_default(): void
    {
        $exception = new InvalidParameterException('attacker_key', 'reason');

        self::assertSame('<redacted>', $exception->parameterName());
    }

    #[Test]
    public function invalid_parameter_exception_getters_return_values_with_reveal(): void
    {
        $exception = new InvalidParameterException('attacker_key', 'reason');

        self::assertSame('attacker_key', $exception->parameterName(reveal: true));
    }

    #[Test]
    public function uri_validator_does_not_interpolate_scheme_in_message(): void
    {
        $validator = new UriValidator();
        $sensitiveScheme = 'javascript';

        // RFC 3986 generic-syntax-valid URIs are accepted regardless of
        // scheme (no allowlist), so a URI that would previously fail the
        // allowlist now passes. Trigger the only remaining throw path
        // (port-out-of-range) while still embedding an attacker-supplied
        // scheme to prove the message is generic (S-021, CWE-209).
        try {
            $validator->validate($sensitiveScheme . '://example.com:99999');
            self::fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $e) {
            self::assertStringNotContainsString(
                $sensitiveScheme,
                $e->getMessage(),
                'getMessage() must not interpolate attacker-controlled scheme (S-021).',
            );
            self::assertStringNotContainsString(
                '99999',
                $e->getMessage(),
                'getMessage() must not interpolate attacker-controlled port (S-021).',
            );
        }
    }

    #[Test]
    public function validator_compiler_uses_var_export_for_property_names(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            required: ['name'],
            additionalProperties: false,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'UserValidator');

        self::assertStringContainsString(
            'var_export($key, true)',
            $code,
            'Generated additionalProperties check must escape attacker key via var_export (S-015).',
        );
        self::assertStringNotContainsString(
            "'Additional property not allowed: ' . \$key",
            $code,
            'Generated code must not concatenate attacker key raw (S-015).',
        );
    }

    private static function classUsesSanitizableTrait(ReflectionClass $reflection): bool
    {
        foreach ($reflection->getTraitNames() as $traitName) {
            if (SanitizableExceptionTrait::class === $traitName) {
                return true;
            }
        }

        $parent = $reflection->getParentClass();
        if (false !== $parent) {
            return self::classUsesSanitizableTrait($parent);
        }

        return false;
    }
}
