<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class StrictFormatsTest extends TestCase
{
    #[Test]
    public function strict_formats_throws_invalid_format_exception_for_unknown_format(): void
    {
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

        $this->expectException(InvalidFormatException::class);

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableStrictFormats()
            ->build()
            ->validateSchema(
                ['email' => 'test@example.com'],
                '#/components/schemas/User',
            );
    }

    #[Test]
    public function strict_formats_exception_carries_format_and_value(): void
    {
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

        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString($yaml)
                ->enableStrictFormats()
                ->build()
                ->validateSchema(
                    ['email' => 'test@example.com'],
                    '#/components/schemas/User',
                );
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('typo', $exception->format);
            $this->assertSame('test@example.com', $exception->value(reveal: true));
        }
    }

    #[Test]
    public function non_strict_formats_allows_unknown_format(): void
    {
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

        $passed = false;

        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString($yaml)
                ->build()
                ->validateSchema(
                    ['email' => 'test@example.com'],
                    '#/components/schemas/User',
                );
            $passed = true;
        } catch (InvalidFormatException $exception) {
            $this->fail(sprintf(
                'Unknown format should be skipped without strict mode, got: %s',
                $exception->getMessage(),
            ));
        }

        $this->assertTrue($passed, 'Validation should pass without strict formats');
    }

    #[Test]
    public function known_format_does_not_throw_in_strict_mode(): void
    {
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

        $passed = false;

        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString($yaml)
                ->enableStrictFormats()
                ->build()
                ->validateSchema(
                    ['email' => 'test@example.com'],
                    '#/components/schemas/User',
                );
            $passed = true;
        } catch (InvalidFormatException $exception) {
            $this->fail(sprintf(
                'Known format should not throw in strict mode, got: %s',
                $exception->getMessage(),
            ));
        }

        $this->assertTrue($passed, 'Known format should pass in strict mode');
    }
}
