<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression coverage for R4-SEC-009: builtin format validators must
 * share the configured PregExecutor regardless of the order in which
 * withFormat() and withMaxRegexBacktracks() are called.
 *
 * @internal
 */
final class OpenApiValidatorBuilderWithFormatTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths: []
YAML;

    private const int CONFIGURED_BACKTRACKS = 1337;

    #[Test]
    public function withFormat_after_withMaxRegexBacktracks_uses_configured_backtrack_limit_for_builtin_email(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withMaxRegexBacktracks(self::CONFIGURED_BACKTRACKS)
            ->withFormat('string', 'phone', $this->noOpValidator())
            ->build();

        $maxBacktracks = $this->extractEmailPregExecutorMaxBacktracks($validator);

        self::assertSame(self::CONFIGURED_BACKTRACKS, $maxBacktracks);
    }

    #[Test]
    public function withFormat_before_withMaxRegexBacktracks_uses_configured_backtrack_limit_for_builtin_email(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'phone', $this->noOpValidator())
            ->withMaxRegexBacktracks(self::CONFIGURED_BACKTRACKS)
            ->build();

        $maxBacktracks = $this->extractEmailPregExecutorMaxBacktracks($validator);

        self::assertSame(self::CONFIGURED_BACKTRACKS, $maxBacktracks);
    }

    #[Test]
    public function without_withFormat_uses_configured_backtrack_limit_for_builtin_email(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withMaxRegexBacktracks(self::CONFIGURED_BACKTRACKS)
            ->build();

        $maxBacktracks = $this->extractEmailPregExecutorMaxBacktracks($validator);

        self::assertSame(self::CONFIGURED_BACKTRACKS, $maxBacktracks);
    }

    #[Test]
    public function withFormat_overriding_builtin_email_replaces_builtin_after_build(): void
    {
        $custom = new class implements FormatValidatorInterface {
            #[Override]
            public function validate(mixed $data): void
            {
                throw new InvalidFormatException('email', $data, 'custom email marker');
            }
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'email', $custom)
            ->build();

        $registry = $this->extractFormatRegistry($validator);

        self::assertSame($custom, $registry->getValidator('string', 'email'));
    }

    #[Test]
    public function withFormat_user_validator_preserved_alongside_builtin(): void
    {
        $custom = $this->noOpValidator();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormat('string', 'phone', $custom)
            ->build();

        $registry = $this->extractFormatRegistry($validator);

        self::assertSame($custom, $registry->getValidator('string', 'phone'));
        self::assertInstanceOf(EmailValidator::class, $registry->getValidator('string', 'email'));
    }

    private function extractFormatRegistry(OpenApiValidator $validator): FormatRegistry
    {
        $validatorRefl = new ReflectionClass($validator);
        $dependencies = $validatorRefl->getProperty('dependencies')->getValue($validator);

        return $dependencies->formatRegistry;
    }

    private function extractEmailPregExecutorMaxBacktracks(OpenApiValidator $validator): int
    {
        $registry = $this->extractFormatRegistry($validator);
        $emailValidator = $registry->getValidator('string', 'email');

        self::assertInstanceOf(EmailValidator::class, $emailValidator);

        $emailRefl = new ReflectionClass($emailValidator);
        $pregExecutor = $emailRefl->getProperty('pregExecutor')->getValue($emailValidator);

        self::assertInstanceOf(PregExecutor::class, $pregExecutor);

        $pregRefl = new ReflectionClass($pregExecutor);
        $maxBacktracks = $pregRefl->getProperty('maxBacktracks')->getValue($pregExecutor);

        self::assertIsInt($maxBacktracks);

        return $maxBacktracks;
    }

    private function noOpValidator(): FormatValidatorInterface
    {
        return new class implements FormatValidatorInterface {
            #[Override]
            public function validate(mixed $data): void {}
        };
    }
}
