<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class StrictFormatsTest extends TestCase
{
    #[Test]
    public function strict_formats_logs_warning_for_unknown_format(): void
    {
        $collectedMessages = [];

        $logger = new class ($collectedMessages) extends AbstractLogger {
            /** @param list<string> $messages */
            public function __construct(
                private array &$messages,
            ) {}

            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        email:
          type: string
          format: typo
      required:
        - email
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableStrictFormats()
            ->withLogger($logger)
            ->build()
            ->validateSchema(
                ['email' => 'test@example.com'],
                '#/components/schemas/User',
            );

        $hasWarning = array_any(
            $collectedMessages,
            fn(string $msg) => str_contains($msg, 'Unknown format "typo"'),
        );

        $this->assertTrue($hasWarning, 'Expected warning about unknown format "typo"');
    }

    #[Test]
    public function non_strict_formats_does_not_log_warning(): void
    {
        $collectedMessages = [];

        $logger = new class ($collectedMessages) extends AbstractLogger {
            /** @param list<string> $messages */
            public function __construct(
                private array &$messages,
            ) {}

            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        email:
          type: string
          format: typo
      required:
        - email
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withLogger($logger)
            ->build()
            ->validateSchema(
                ['email' => 'test@example.com'],
                '#/components/schemas/User',
            );

        $hasFormatWarning = array_any(
            $collectedMessages,
            fn(string $msg) => str_contains($msg, 'Unknown format'),
        );

        $this->assertFalse($hasFormatWarning, 'Should not have warning without strict formats');
    }

    #[Test]
    public function known_format_does_not_log_warning_in_strict_mode(): void
    {
        $collectedMessages = [];

        $logger = new class ($collectedMessages) extends AbstractLogger {
            /** @param list<string> $messages */
            public function __construct(
                private array &$messages,
            ) {}

            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        email:
          type: string
          format: email
      required:
        - email
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableStrictFormats()
            ->withLogger($logger)
            ->build()
            ->validateSchema(
                ['email' => 'test@example.com'],
                '#/components/schemas/User',
            );

        $hasFormatWarning = array_any(
            $collectedMessages,
            fn(string $msg) => str_contains($msg, 'Unknown format'),
        );

        $this->assertFalse($hasFormatWarning, 'Known format should not produce warning');
    }
}
