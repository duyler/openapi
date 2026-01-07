<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NotValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private NotValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new NotValidator($this->pool);
    }

    #[Test]
    public function validate_when_schema_invalid(): void
    {
        $notSchema = new Schema(type: 'string', minLength: 10);
        $schema = new Schema(not: $notSchema);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_schema_valid(): void
    {
        $notSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_when_not_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_complex_not_schema(): void
    {
        $notSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(not: $notSchema);

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_matching_not_schema(): void
    {
        $notSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate(15, $schema);
    }

    #[Test]
    public function validate_with_type_restriction(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_prohibited_type(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }
}
