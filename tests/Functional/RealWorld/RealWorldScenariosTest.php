<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\RealWorld;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Functional\FunctionalTestCase;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use PHPUnit\Framework\Attributes\Test;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\RequiredError;

final class RealWorldScenariosTest extends FunctionalTestCase
{
    // Pagination scenarios
    #[Test]
    public function pagination_with_valid_parameters(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'page' => new Schema(
                    type: 'integer',
                    minimum: 1,
                    default: 1,
                ),
                'limit' => new Schema(
                    type: 'integer',
                    minimum: 1,
                    maximum: 100,
                    default: 20,
                ),
                'offset' => new Schema(
                    type: 'integer',
                    minimum: 0,
                    default: 0,
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['page' => 2, 'limit' => 50, 'offset' => 50],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function pagination_with_invalid_page_zero(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'page' => new Schema(type: 'integer', minimum: 1),
                'limit' => new Schema(type: 'integer', minimum: 1, maximum: 100),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['page' => 0, 'limit' => 20],
                $schema,
                $context,
            ),
            MinimumError::class,
        );
    }

    #[Test]
    public function pagination_with_limit_exceeds_maximum(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'limit' => new Schema(type: 'integer', maximum: 100),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['limit' => 150],
                $schema,
                $context,
            ),
            MaximumError::class,
        );
    }

    // Filtering scenarios
    #[Test]
    public function filtering_with_valid_enum(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'status' => new Schema(
                    type: 'string',
                    enum: ['active', 'inactive', 'pending'],
                ),
                'category' => new Schema(type: 'string'),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['status' => 'active', 'category' => 'books'],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function filtering_with_invalid_enum(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'status' => new Schema(
                    type: 'string',
                    enum: ['active', 'inactive', 'pending'],
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['status' => 'deleted'],
                $schema,
                $context,
            ),
            EnumError::class,
        );
    }

    #[Test]
    public function filtering_with_range_parameters(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'minPrice' => new Schema(type: 'number', minimum: 0),
                'maxPrice' => new Schema(type: 'number', minimum: 0),
                'dateFrom' => new Schema(type: 'string', format: 'date'),
                'dateTo' => new Schema(type: 'string', format: 'date'),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                [
                    'minPrice' => 10.5,
                    'maxPrice' => 99.99,
                    'dateFrom' => '2024-01-01',
                    'dateTo' => '2024-12-31',
                ],
                $schema,
                $context,
            ),
        );
    }

    // Sorting scenarios
    #[Test]
    public function sorting_with_valid_parameters(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'sort' => new Schema(
                    type: 'string',
                    enum: ['name', 'date', 'price', 'rating'],
                ),
                'order' => new Schema(
                    type: 'string',
                    enum: ['asc', 'desc'],
                    default: 'asc',
                ),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['sort' => 'price', 'order' => 'desc'],
                $schema,
                $context,
            ),
        );
    }

    // Search scenarios
    #[Test]
    public function search_with_valid_query(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['query'],
            properties: [
                'query' => new Schema(
                    type: 'string',
                    minLength: 2,
                    maxLength: 100,
                ),
                'page' => new Schema(type: 'integer', minimum: 1),
                'limit' => new Schema(type: 'integer', minimum: 1, maximum: 50),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['query' => 'search term', 'page' => 1, 'limit' => 20],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function search_with_too_short_query(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['query'],
            properties: [
                'query' => new Schema(type: 'string', minLength: 2),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['query' => 'a'],
                $schema,
                $context,
            ),
            MinLengthError::class,
        );
    }

    // CRUD operations
    #[Test]
    public function create_user_with_valid_data(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['email', 'password', 'name'],
            properties: [
                'email' => new Schema(type: 'string', format: 'email'),
                'password' => new Schema(
                    type: 'string',
                    minLength: 8,
                    maxLength: 128,
                    pattern: '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)',
                ),
                'name' => new Schema(type: 'string', minLength: 2, maxLength: 100),
                'age' => new Schema(type: 'integer', minimum: 18, maximum: 120),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                [
                    'email' => 'user@example.com',
                    'password' => 'SecurePass123',
                    'name' => 'John Doe',
                    'age' => 30,
                ],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function create_user_with_missing_required_field(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['email', 'password', 'name'],
            properties: [
                'email' => new Schema(type: 'string', format: 'email'),
                'password' => new Schema(type: 'string', minLength: 8),
                'name' => new Schema(type: 'string', minLength: 2),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['email' => 'user@example.com', 'password' => 'password123'],
                $schema,
                $context,
            ),
            RequiredError::class,
        );
    }

    #[Test]
    public function create_user_with_invalid_email(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['email'],
            properties: [
                'email' => new Schema(type: 'string', format: 'email'),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        // Email validation throws InvalidFormatException directly, not caught by validation
        $this->expectException(InvalidFormatException::class);
        $this->createValidator()->validateWithContext(
            ['email' => 'not-an-email'],
            $schema,
            $context,
        );
    }

    #[Test]
    public function partial_update_user(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 2, maxLength: 100),
                'phone' => new Schema(
                    type: 'string',
                    pattern: '^\\+?[1-9]\\d{1,14}$',
                ),
                'bio' => new Schema(type: 'string', maxLength: 500),
            ],
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                ['name' => 'Jane Doe'],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function bulk_operations_with_array_of_objects(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                required: ['id', 'name'],
                properties: [
                    'id' => new Schema(type: 'integer'),
                    'name' => new Schema(type: 'string'),
                ],
            ),
            maxItems: 100,
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationPasses(
            fn() => $this->createValidator()->validateWithContext(
                [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                    ['id' => 3, 'name' => 'Item 3'],
                ],
                $schema,
                $context,
            ),
        );
    }

    #[Test]
    public function bulk_operation_exceeds_max_items(): void
    {
        $schema = new Schema(
            type: 'array',
            maxItems: 5,
            items: new Schema(type: 'string'),
        );
        $context = $this->createContext(new SimpleFormatter());

        $this->assertValidationError(
            fn() => $this->createValidator()->validateWithContext(
                ['a', 'b', 'c', 'd', 'e', 'f'],
                $schema,
                $context,
            ),
            MaxItemsError::class,
        );
    }
}
