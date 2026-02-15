<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional;

use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function is_array;

abstract class FunctionalTestCase extends TestCase
{
    protected ValidatorPool $pool;
    protected RefResolverInterface $refResolver;
    protected OpenApiDocument $document;
    protected SchemaParserInterface $parser;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
        );
        $this->refResolver = new RefResolver();
        $this->parser = new YamlParser();
    }

    /**
     * Load fixture from YAML file
     */
    protected function loadFixture(string $path): OpenApiDocument
    {
        $fullPath = __DIR__ . '/../fixtures/' . $path;

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Fixture file not found: {$fullPath}");
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read fixture file: {$fullPath}");
        }

        $document = $this->parser->parse($content);

        if ($document === null) {
            throw new RuntimeException("Failed to parse fixture file: {$fullPath}");
        }

        return $document;
    }

    /**
     * Create a validation context with specified formatter
     */
    protected function createContext(ErrorFormatterInterface $formatter = new SimpleFormatter()): ValidationContext
    {
        return new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
    }

    /**
     * Create a validator instance
     */
    protected function createValidator(): SchemaValidatorWithContext
    {
        return new SchemaValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
        );
    }

    /**
     * Validate data and expect ValidationException
     */
    protected function assertValidationError(
        callable $validationFn,
        string $expectedErrorClass,
        ?string $expectedMessagePattern = null,
    ): void {
        try {
            $validationFn();
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors, 'ValidationException should contain at least one error');

            if ($expectedErrorClass !== null) {
                $found = false;
                foreach ($errors as $error) {
                    if ($error instanceof $expectedErrorClass) {
                        $found = true;
                        if ($expectedMessagePattern !== null) {
                            $this->assertStringContainsString(
                                $expectedMessagePattern,
                                $error->message(),
                                'Error message should contain expected pattern',
                            );
                        }
                        break;
                    }
                }
                $this->assertTrue($found, "Expected error class {$expectedErrorClass} not found in validation errors");
            }
        }
    }

    /**
     * Assert that validation passes without errors
     */
    protected function assertValidationPasses(callable $validationFn): void
    {
        try {
            $validationFn();
            $this->assertTrue(true, 'Validation passed as expected');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->message();
            }
            $this->fail(
                'Expected validation to pass, but got errors: '
                . implode('; ', $messages),
            );
        }
    }

    /**
     * Assert that multiple errors are present
     */
    protected function assertMultipleErrors(
        callable $validationFn,
        int $expectedCount,
        ?string $expectedMessagePattern = null,
    ): void {
        try {
            $validationFn();
            $this->fail('Expected ValidationException with multiple errors');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount($expectedCount, $errors, "Expected {$expectedCount} errors");

            if ($expectedMessagePattern !== null) {
                $found = array_any($errors, fn($error) => str_contains((string) $error->message(), $expectedMessagePattern));
                $this->assertTrue($found, "Expected error message pattern '{$expectedMessagePattern}' not found");
            }
        }
    }

    /**
     * Get formatted errors as array
     */
    protected function getFormattedErrors(ValidationException $e, ErrorFormatterInterface $formatter): array
    {
        $errors = [];
        foreach ($e->getErrors() as $error) {
            $formatted = $formatter->format($error);

            if ($formatter instanceof JsonFormatter) {
                $decoded = json_decode($formatted, true);
                if (is_array($decoded)) {
                    $errors[] = $decoded;
                }
            } else {
                $errors[] = $formatted;
            }
        }

        return $errors;
    }

    /**
     * Assert breadcrumb path in error
     */
    protected function assertBreadcrumb(string $expectedPath, ValidationException $e): void
    {
        $found = array_any($e->getErrors(), fn($error) => $error->dataPath() === $expectedPath);
        $this->assertTrue(
            $found,
            "Expected breadcrumb path '{$expectedPath}' not found in errors: "
            . $this->getErrorPathsSummary($e),
        );
    }

    /**
     * Get summary of error paths for debugging
     */
    protected function getErrorPathsSummary(ValidationException $e): string
    {
        $paths = [];
        foreach ($e->getErrors() as $error) {
            $paths[] = $error->dataPath();
        }

        return implode(', ', $paths);
    }

    /**
     * Get all error types from exception
     */
    protected function getErrorTypes(ValidationException $e): array
    {
        $types = [];
        foreach ($e->getErrors() as $error) {
            $types[] = $error::class;
        }

        return $types;
    }
}
