<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
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
}
