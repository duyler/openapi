<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReadOnlyWriteOnlyValidatorTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function read_only_property_in_request_throws_exception(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $data = ['id' => '123', 'name' => 'test'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('read-only');

        $validator->validate($data, $schema, $context);
    }

    #[Test]
    public function write_only_property_in_response_throws_exception(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'password' => new Schema(type: 'string', writeOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Response,
        );

        $data = ['password' => 'secret', 'name' => 'test'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('write-only');

        $validator->validate($data, $schema, $context);
    }

    #[Test]
    public function read_only_property_in_response_is_allowed(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Response,
        );

        $data = ['id' => '123', 'name' => 'test'];

        $validator->validate($data, $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function write_only_property_in_request_is_allowed(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'password' => new Schema(type: 'string', writeOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $data = ['password' => 'secret', 'name' => 'test'];

        $validator->validate($data, $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function no_mode_skips_validation(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
            ],
        );

        $data = ['id' => '123'];

        $validator->validate($data, $schema, null);

        $this->assertTrue(true);
    }

    #[Test]
    public function read_only_property_absent_in_request_succeeds(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $data = ['name' => 'test'];

        $validator->validate($data, $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function non_object_data_skips_validation(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'string',
            readOnly: true,
        );

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $validator->validate('some string', $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function schema_without_properties_skips_validation(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(type: 'object');

        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $validator->validate(['foo' => 'bar'], $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function combined_read_write_properties_in_correct_contexts(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator($this->pool, BuiltinFormats::instance());
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
                'password' => new Schema(type: 'string', writeOnly: true),
                'name' => new Schema(type: 'string'),
            ],
        );

        $requestContext = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Request,
        );

        $validator->validate(['password' => 'secret', 'name' => 'test'], $schema, $requestContext);

        $responseContext = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            mode: ValidatorMode::Response,
        );

        $validator->validate(['id' => '123', 'name' => 'test'], $schema, $responseContext);

        $this->assertTrue(true);
    }
}
