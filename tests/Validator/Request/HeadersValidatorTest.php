<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class HeadersValidatorTest extends TestCase
{
    private HeadersValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);

        $this->validator = new HeadersValidator($schemaValidator);
    }

    #[Test]
    public function validate_headers(): void
    {
        $headers = ['X-Custom-Header' => 'value'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function use_case_insensitive_matching(): void
    {
        $headers = ['content-type' => 'application/json'];
        $headerSchemas = [
            new Parameter(
                name: 'Content-Type',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_required_header(): void
    {
        $headers = [];
        $headerSchemas = [
            new Parameter(
                name: 'Authorization',
                in: 'header',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_headers_with_type_validation(): void
    {
        $headers = ['X-Custom-Header' => '123'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_type(): void
    {
        $headers = ['X-Custom-Header' => '123'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                schema: new Schema(type: 'integer'),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_headers_with_format_validation(): void
    {
        $headers = ['X-Email' => 'test@example.com'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Email',
                in: 'header',
                schema: new Schema(type: 'string', format: 'email'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_multiple_values(): void
    {
        $headers = ['X-Custom-Header' => ['value1', 'value2', 'value3']];
        $headerSchemas = [
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_multiple_values_joined(): void
    {
        $headers = ['Accept' => ['application/json', 'application/xml']];
        $headerSchemas = [
            new Parameter(
                name: 'Accept',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_with_min_length(): void
    {
        $headers = ['X-Token' => 'valid-token'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Token',
                in: 'header',
                schema: new Schema(type: 'string', minLength: 5),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_min_length_constraint(): void
    {
        $headers = ['X-Token' => 'abc'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Token',
                in: 'header',
                schema: new Schema(type: 'string', minLength: 5),
            ),
        ];

        $this->expectException(MinLengthError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_headers_with_max_length(): void
    {
        $headers = ['X-Token' => 'abc'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Token',
                in: 'header',
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_max_length_constraint(): void
    {
        $headers = ['X-Token' => 'very-long-token-value'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Token',
                in: 'header',
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->expectException(MaxLengthError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function validate_headers_with_pattern(): void
    {
        $headers = ['X-Code' => 'ABC123'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Code',
                in: 'header',
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_pattern_constraint(): void
    {
        $headers = ['X-Code' => 'invalid'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Code',
                in: 'header',
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function skip_missing_optional_headers(): void
    {
        $headers = ['X-Required' => 'value'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Required',
                in: 'header',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'X-Optional',
                in: 'header',
                required: false,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_non_header_parameters(): void
    {
        $headers = ['X-Custom-Header' => 'value'];
        $headerSchemas = [
            new Parameter(
                name: 'query',
                in: 'query',
                required: true,
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_case_insensitive_lowercase(): void
    {
        $headers = ['x-custom-header' => 'value'];
        $headerSchemas = [
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_case_insensitive_uppercase(): void
    {
        $headers = ['CONTENT-TYPE' => 'application/json'];
        $headerSchemas = [
            new Parameter(
                name: 'Content-Type',
                in: 'header',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_headers_multiple_headers(): void
    {
        $headers = [
            'Authorization' => 'Bearer token',
            'X-Custom-Header' => 'value',
            'X-Another-Header' => 'another-value',
        ];
        $headerSchemas = [
            new Parameter(
                name: 'Authorization',
                in: 'header',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'X-Custom-Header',
                in: 'header',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'X-Another-Header',
                in: 'header',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }
}
