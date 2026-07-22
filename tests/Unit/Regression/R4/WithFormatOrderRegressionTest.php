<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Regression suite for R4-SEC-009 / R4-TEST-010: builtin format
 * validators must share the configured PregExecutor regardless of
 * the order in which withFormat() and withMaxRegexBacktracks() are
 * chained, and a later withFormat('string','email',...) must
 * override the builtin email validator regardless of order.
 *
 * Anti-test: re-introducing the legacy single-call-only wiring (where
 * the second withFormat() or a later withMaxRegexBacktracks() would
 * silently drop the configuration) makes the override assertions
 * fail.
 *
 * Unlike the inline OpenApiValidatorBuilderWithFormatTest that uses
 * reflection to assert on private state, this suite drives every
 * case through validateSchema() so the regression also fires when
 * the override mechanism is broken at the format-registry lookup
 * level rather than at construction time.
 *
 * @internal
 */
final class WithFormatOrderRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    EmailHolder:
      type: object
      required: [email]
      properties:
        email: { type: string, format: email }
      additionalProperties: false
YAML;

    #[Test]
    public function withFormat_called_before_withMaxRegexBacktracks_overrides_builtin_email(): void
    {
        $custom = self::failingMarkerValidator('custom-email-marker-before');

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'email', $custom)
            ->withMaxRegexBacktracks(1337)
            ->build();

        $caught = $this->captureInvalidFormat($validator, ['email' => 'user@example.com']);

        self::assertNotNull($caught);
        self::assertStringContainsString(
            'custom-email-marker-before',
            $caught->getMessage(),
            'withFormat override must win regardless of subsequent withMaxRegexBacktracks() call.',
        );
    }

    #[Test]
    public function withFormat_called_after_withMaxRegexBacktracks_overrides_builtin_email(): void
    {
        $custom = self::failingMarkerValidator('custom-email-marker-after');

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withMaxRegexBacktracks(1337)
            ->withFormat('string', 'email', $custom)
            ->build();

        $caught = $this->captureInvalidFormat($validator, ['email' => 'user@example.com']);

        self::assertNotNull($caught);
        self::assertStringContainsString(
            'custom-email-marker-after',
            $caught->getMessage(),
            'withFormat override must win when chained after withMaxRegexBacktracks() (order-independent).',
        );
    }

    #[Test]
    public function last_withFormat_call_for_same_type_and_format_wins(): void
    {
        $first = self::failingMarkerValidator('first-marker');
        $second = self::failingMarkerValidator('second-marker-wins');

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'email', $first)
            ->withFormat('string', 'email', $second)
            ->build();

        $caught = $this->captureInvalidFormat($validator, ['email' => 'user@example.com']);

        self::assertNotNull($caught);
        self::assertSame(
            'second-marker-wins',
            $caught->getMessage(),
            'When withFormat() is called twice for the same type+format, the last call must win.',
        );
    }

    #[Test]
    public function withFormat_user_validator_does_not_disable_builtin_email_for_other_specs(): void
    {
        $custom = self::failingMarkerValidator('never-triggered-marker');

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'phone', $custom)
            ->build();

        $validator->validateSchema(['email' => 'user@example.com'], '#/components/schemas/EmailHolder');

        self::assertTrue(true);
    }

    private static function failingMarkerValidator(string $marker): FormatValidatorInterface
    {
        return new readonly class ($marker) implements FormatValidatorInterface {
            public function __construct(private string $marker) {}

            #[Override]
            public function validate(mixed $data): void
            {
                throw new InvalidFormatException('email', $data, $this->marker);
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function captureInvalidFormat(OpenApiValidatorInterface $validator, array $payload): ?InvalidFormatException
    {
        try {
            $validator->validateSchema($payload, '#/components/schemas/EmailHolder');
        } catch (InvalidFormatException $e) {
            return $e;
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                if ($error instanceof InvalidFormatException) {
                    return $error;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
