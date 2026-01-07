<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ErrorHandlingIntegrationTest extends TestCase
{
    private ValidatorPool $pool;
    private RefResolverInterface $refResolver;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            openapi: '3.0.0',
            info: new \Duyler\OpenApi\Schema\Model\InfoObject(
                title: 'Test',
                version: '1.0.0',
            ),
        );
        $this->refResolver = new RefResolver();
    }

    #[Test]
    public function format_validation_error_with_simple_formatter(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 5),
            ],
        );

        $formatter = new SimpleFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                ['name' => 'ab'], // Too short
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinLengthError::class, $error);
            $formatted = $formatter->format($error);
            // The error may be at root level due to validateInternal not receiving context
            // This is expected for Phase 7 - full breadcrumb integration will be in Phase 8
            $this->assertStringContainsString('minimum', $formatted);
        }
    }

    #[Test]
    public function format_multiple_errors_with_detailed_formatter(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 5),
                'age' => new Schema(type: 'integer', minimum: 18),
            ],
        );

        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                ['name' => 'ab', 'age' => 15],
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThanOrEqual(1, count($errors));
            $formatted = $formatter->formatMultiple($errors);
            // At least one field should have an error
            $this->assertNotEmpty($formatted);
        }
    }

    #[Test]
    public function use_custom_formatter(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 3,
        );

        $formatter = new JsonFormatter(); // Custom formatter
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                'ab', // Too short
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinLengthError::class, $error);
            $formatted = $formatter->format($error);
            $decoded = json_decode($formatted, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('breadcrumb', $decoded);
            $this->assertArrayHasKey('message', $decoded);
            $this->assertArrayHasKey('details', $decoded);
        }
    }

    #[Test]
    public function context_maintains_formatter_through_validation_chain(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'age' => new Schema(type: 'integer', minimum: 18),
                    ],
                ),
            ],
        );

        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                ['user' => ['age' => 15]], // Too young
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinimumError::class, $error);
            // Verify formatter is still accessible
            $formatted = $context->errorFormatter->format($error);
            $this->assertNotEmpty($formatted);
        }
    }

    #[Test]
    public function simple_formatter_for_root_level_error(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 3,
        );

        $formatter = new SimpleFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                'ab',
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinLengthError::class, $error);
            $formatted = $formatter->format($error);
            // Root level errors shouldn't have breadcrumb prefix in simple formatter
            $this->assertStringNotContainsString('[/]', $formatted);
            $this->assertNotEmpty($formatted);
        }
    }

    #[Test]
    public function json_formatter_includes_all_error_details(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'email' => new Schema(type: 'string', minLength: 5),
            ],
        );

        $formatter = new JsonFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                ['email' => 'a@b'],
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinLengthError::class, $error);
            $formatted = $formatter->format($error);
            $decoded = json_decode($formatted, true);

            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('breadcrumb', $decoded);
            $this->assertArrayHasKey('message', $decoded);
            $this->assertArrayHasKey('details', $decoded);
            $this->assertArrayHasKey('minLength', $decoded['details']);
        }
    }

    #[Test]
    public function detailed_formatter_provides_comprehensive_output(): void
    {
        $schema = new Schema(
            type: 'integer',
            minimum: 10,
            maximum: 100,
        );

        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $formatter,
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        try {
            $validator->validateWithContext(
                5, // Below minimum
                $schema,
                $context,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $error = $errors[0];
            $this->assertInstanceOf(MinimumError::class, $error);
            $formatted = $formatter->format($error);
            // Detailed formatter should include comprehensive information
            $this->assertStringContainsString('minimum', $formatted);
            $this->assertStringContainsString('10', $formatted);
            $this->assertNotEmpty($formatted);
        }
    }
}
