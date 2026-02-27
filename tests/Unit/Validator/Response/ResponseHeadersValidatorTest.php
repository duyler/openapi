<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ResponseHeadersValidatorTest extends TestCase
{
    private ResponseHeadersValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);

        $this->validator = new ResponseHeadersValidator($schemaValidator);
    }

    #[Test]
    public function validate_response_headers(): void
    {
        $headers = ['X-Rate-Limit' => '100'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_valid(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $headerSchemas = new Headers([
            'Content-Type' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_with_type_validation(): void
    {
        $headers = ['X-Custom-String' => '123'];
        $headerSchemas = new Headers([
            'X-Custom-String' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_missing_required(): void
    {
        $headers = ['X-Optional' => 'value'];
        $headerSchemas = new Headers([
            'X-Required' => new Header(required: true),
            'X-Optional' => new Header(required: false),
        ]);

        $this->expectException(MissingParameterException::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_response_headers_invalid_type_throws_exception(): void
    {
        $headers = ['X-Number' => 'not_a_number'];
        $headerSchemas = new Headers([
            'X-Number' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_response_headers_with_schema(): void
    {
        $headers = ['X-Id' => '42'];
        $headerSchemas = new Headers([
            'X-Id' => new Header(
                schema: new Schema(
                    type: 'string',
                    minLength: 1,
                    maxLength: 10,
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_with_format_validation(): void
    {
        $headers = ['X-Email' => 'test@example.com'];
        $headerSchemas = new Headers([
            'X-Email' => new Header(
                schema: new Schema(
                    type: 'string',
                    format: 'email',
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_case_insensitive(): void
    {
        $headers = ['content-type' => 'application/json'];
        $headerSchemas = new Headers([
            'Content-Type' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_headers_empty(): void
    {
        $headers = [];
        $headerSchemas = new Headers([
            'X-Optional' => new Header(required: false),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function use_case_insensitive_matching(): void
    {
        $headers = ['x-rate-limit' => '100'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_required_header(): void
    {
        $headers = [];
        $headerSchemas = new Headers([
            'X-Required-Header' => new Header(
                required: true,
            ),
        ]);

        $this->expectException(MissingParameterException::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function skip_validation_when_header_schemas_is_null(): void
    {
        $headers = ['X-Custom' => 'value'];

        $this->validator->validate($headers, null);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handle_array_header_values(): void
    {
        $headers = ['Set-Cookie' => ['session=abc', 'theme=dark']];
        $headerSchemas = new Headers([
            'Set-Cookie' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_optional_header_when_not_present(): void
    {
        $headers = ['X-Present' => 'value'];
        $headerSchemas = new Headers([
            'X-Present' => new Header(
                required: false,
                schema: new Schema(type: 'string'),
            ),
            'X-Optional' => new Header(required: false),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function handle_numeric_array_keys(): void
    {
        $numericArray = [0 => 'ignored', 'X-Custom' => 'value'];
        $headerSchemas = new Headers([
            'X-Custom' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($numericArray, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_header(): void
    {
        $headers = ['X-Rate-Limit' => '100'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_header_with_minimum(): void
    {
        $headers = ['X-Rate-Limit' => '50'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(
                    type: 'integer',
                    minimum: 0,
                    maximum: 100,
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_header_above_maximum_throws_error(): void
    {
        $headers = ['X-Rate-Limit' => '150'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(
                    type: 'integer',
                    maximum: 100,
                ),
            ),
        ]);

        $this->expectException(MaximumError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_integer_header_below_minimum_throws_error(): void
    {
        $headers = ['X-Rate-Limit' => '-1'];
        $headerSchemas = new Headers([
            'X-Rate-Limit' => new Header(
                schema: new Schema(
                    type: 'integer',
                    minimum: 0,
                ),
            ),
        ]);

        $this->expectException(MinimumError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_integer_header_invalid_throws_error(): void
    {
        $headers = ['X-Number' => 'not-a-number'];
        $headerSchemas = new Headers([
            'X-Number' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_number_header(): void
    {
        $headers = ['X-Price' => '99.99'];
        $headerSchemas = new Headers([
            'X-Price' => new Header(
                schema: new Schema(type: 'number'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_number_header_integer_value(): void
    {
        $headers = ['X-Count' => '42'];
        $headerSchemas = new Headers([
            'X-Count' => new Header(
                schema: new Schema(type: 'number'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_number_header_invalid_throws_error(): void
    {
        $headers = ['X-Price' => 'not-a-number'];
        $headerSchemas = new Headers([
            'X-Price' => new Header(
                schema: new Schema(type: 'number'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_boolean_header_true(): void
    {
        $headers = ['X-Enabled' => 'true'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_false(): void
    {
        $headers = ['X-Enabled' => 'false'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_one(): void
    {
        $headers = ['X-Enabled' => '1'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_zero(): void
    {
        $headers = ['X-Enabled' => '0'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_yes(): void
    {
        $headers = ['X-Enabled' => 'yes'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_no(): void
    {
        $headers = ['X-Enabled' => 'no'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_on(): void
    {
        $headers = ['X-Enabled' => 'on'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_off(): void
    {
        $headers = ['X-Enabled' => 'off'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_header_case_insensitive(): void
    {
        $headers = ['X-Enabled' => 'TRUE'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_simple(): void
    {
        $headers = ['Content-Encoding' => 'gzip, deflate'];
        $headerSchemas = new Headers([
            'Content-Encoding' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_without_spaces(): void
    {
        $headers = ['Content-Encoding' => 'gzip,deflate'];
        $headerSchemas = new Headers([
            'Content-Encoding' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_with_extra_spaces(): void
    {
        $headers = ['Content-Encoding' => 'gzip,  deflate'];
        $headerSchemas = new Headers([
            'Content-Encoding' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_multiple_values(): void
    {
        $headers = ['Allow' => 'GET, POST, PUT, DELETE'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_single_value(): void
    {
        $headers = ['Allow' => 'GET'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_empty_values(): void
    {
        $headers = ['Allow' => 'GET, POST,'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_header_from_array_value(): void
    {
        $headers = ['Set-Cookie' => ['session=abc', 'theme=dark']];
        $headerSchemas = new Headers([
            'Set-Cookie' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_content_length(): void
    {
        $headers = ['Content-Length' => '1234'];
        $headerSchemas = new Headers([
            'Content-Length' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_allow_header(): void
    {
        $headers = ['Allow' => 'GET, POST, PUT, DELETE'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_enabled_header(): void
    {
        $headers = ['X-Enabled' => 'true'];
        $headerSchemas = new Headers([
            'X-Enabled' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_header_unchanged(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $headerSchemas = new Headers([
            'Content-Type' => new Header(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_with_float_value(): void
    {
        $headers = ['X-Value' => '30.5'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_zero(): void
    {
        $headers = ['X-Value' => '0'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_number_zero(): void
    {
        $headers = ['X-Value' => '0'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'number'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_number_negative(): void
    {
        $headers = ['X-Value' => '-42.5'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'number'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_integer_negative(): void
    {
        $headers = ['X-Value' => '-42'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_with_min_max_items(): void
    {
        $headers = ['Allow' => 'GET, POST'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    minItems: 1,
                    maxItems: 5,
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_array_too_many_items_throws_error(): void
    {
        $headers = ['Allow' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS'];
        $headerSchemas = new Headers([
            'Allow' => new Header(
                schema: new Schema(
                    type: 'array',
                    maxItems: 5,
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->expectException(MaxItemsError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_integer_with_non_numeric_value_throws_error(): void
    {
        $headers = ['X-Value' => 'abc123'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'integer'),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function coerce_value_unknown_type_returns_unchanged(): void
    {
        $headers = ['X-Value' => 'some-value'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(
                    type: ['string', 'integer'],
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function coerce_boolean_with_other_value(): void
    {
        $headers = ['X-Value' => 'other-value'];
        $headerSchemas = new Headers([
            'X-Value' => new Header(
                schema: new Schema(type: 'boolean'),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }
}
