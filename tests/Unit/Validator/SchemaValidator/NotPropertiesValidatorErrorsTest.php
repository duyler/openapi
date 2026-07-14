<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\NotValidationError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(NotValidator::class)]
#[CoversClass(PropertiesValidator::class)]
#[CoversClass(NotValidationError::class)]
final class NotPropertiesValidatorErrorsTest extends TestCase
{
    private ValidatorPool $pool;
    private NotValidator $notValidator;
    private PropertiesValidator $propertiesValidator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->notValidator = new NotValidator($this->pool, BuiltinFormats::create());
        $this->propertiesValidator = new PropertiesValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function not_validator_violation_has_errors_array(): void
    {
        $schema = new Schema(not: new Schema(type: 'string'));

        $caught = null;

        try {
            $this->notValidator->validate('matches', $schema);
            self::fail('Expected ValidationException when data matches the "not" schema');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertSame('Data must NOT match the "not" schema', $caught->getMessage());
        self::assertCount(1, $errors);
        self::assertInstanceOf(NotValidationError::class, $errors[0]);
        self::assertSame('not', $errors[0]->keyword());
        self::assertSame('/not', $errors[0]->schemaPath());
    }

    #[Test]
    public function properties_validator_nested_type_mismatch_wrapped_in_validation_exception(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    type: 'object',
                    properties: [
                        'zip' => new Schema(type: 'integer'),
                    ],
                ),
            ],
        );

        $caught = null;

        try {
            $this->propertiesValidator->validate(
                ['address' => ['zip' => 'not-an-integer']],
                $schema,
            );
            self::fail('Expected ValidationException wrapping nested TypeMismatchError');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('integer', $errors[0]->params()['expected']);
        self::assertSame('string', $errors[0]->params()['actual']);
    }

    #[Test]
    public function properties_validator_nested_validation_exception_propagates_errors(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 10),
            ],
        );

        $caught = null;

        try {
            $this->propertiesValidator->validate(['name' => 'short'], $schema);
            self::fail('Expected ValidationException propagating nested errors');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(MinLengthError::class, $errors[0]);
        self::assertSame('minLength', $errors[0]->keyword());
    }
}
