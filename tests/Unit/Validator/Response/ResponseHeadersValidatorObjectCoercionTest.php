<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

/** @internal */
final class ResponseHeadersValidatorObjectCoercionTest extends TestCase
{
    private ResponseHeadersValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());

        $this->validator = new ResponseHeadersValidator($schemaValidator);
    }

    #[Test]
    public function empty_value_returns_empty_array(): void
    {
        $headers = ['X-Meta' => ''];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_value_coerces_to_empty_array_proven_by_reject_strategy(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());
        $validator = new ResponseHeadersValidator(
            $schemaValidator,
            $pool,
            new ParameterValidationConfig(emptyArrayStrategy: EmptyArrayStrategy::Reject),
        );

        $headers = ['X-Meta' => ''];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function valid_json_object_returns_decoded_array(): void
    {
        $headers = ['X-Meta' => '{"a":1}'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(
                    type: 'object',
                    properties: ['a' => new Schema(type: 'integer')],
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function json_empty_object_passes(): void
    {
        $headers = ['X-Meta' => '{}'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function invalid_json_throws_type_mismatch(): void
    {
        $headers = ['X-Meta' => 'abc'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function json_scalar_throws_type_mismatch(): void
    {
        $headers = ['X-Meta' => '123'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function json_array_throws_type_mismatch(): void
    {
        $headers = ['X-Meta' => '[1,2,3]'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function json_null_throws_type_mismatch(): void
    {
        $headers = ['X-Meta' => 'null'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function json_boolean_throws_type_mismatch(): void
    {
        $headers = ['X-Meta' => 'true'];
        $headerSchemas = new Headers([
            'X-Meta' => new Header(
                schema: new Schema(type: 'object'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }
}
