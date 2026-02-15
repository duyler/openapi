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
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CookieValidatorTest extends TestCase
{
    private CookieValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $this->validator = new CookieValidator($schemaValidator, $deserializer, $coercer);
    }

    #[Test]
    public function parse_cookies(): void
    {
        $result = $this->validator->parseCookies('name=value; name2=value2');

        $this->assertSame(['name' => 'value', 'name2' => 'value2'], $result);
    }

    #[Test]
    public function parse_empty_cookie_header(): void
    {
        $result = $this->validator->parseCookies('');

        $this->assertSame([], $result);
    }

    #[Test]
    public function validate_cookie_params(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_required_cookie(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function validate_cookies_valid(): void
    {
        $cookies = ['session' => 'abc123', 'user' => 'john'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'user',
                in: 'cookie',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_cookies_with_type_validation(): void
    {
        $cookies = ['count' => '10'];
        $parameterSchemas = [
            new Parameter(
                name: 'count',
                in: 'cookie',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_type(): void
    {
        $cookies = ['count' => '10'];
        $parameterSchemas = [
            new Parameter(
                name: 'count',
                in: 'cookie',
                schema: new Schema(type: 'integer'),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function validate_cookies_with_format_validation(): void
    {
        $cookies = ['email' => 'test@example.com'];
        $parameterSchemas = [
            new Parameter(
                name: 'email',
                in: 'cookie',
                schema: new Schema(type: 'string', format: 'email'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_cookies_empty(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'optional',
                in: 'cookie',
                required: false,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_cookies_with_schema(): void
    {
        $cookies = ['token' => 'abc123xyz'];
        $parameterSchemas = [
            new Parameter(
                name: 'token',
                in: 'cookie',
                schema: new Schema(
                    type: 'string',
                    minLength: 5,
                    maxLength: 50,
                ),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_cookies_without_schema(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_minimum_constraint(): void
    {
        $cookies = ['page' => '0'];
        $parameterSchemas = [
            new Parameter(
                name: 'page',
                in: 'cookie',
                schema: new Schema(type: 'integer', minimum: 1),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function throw_error_for_maximum_constraint(): void
    {
        $cookies = ['page' => '101'];
        $parameterSchemas = [
            new Parameter(
                name: 'page',
                in: 'cookie',
                schema: new Schema(type: 'integer', maximum: 100),
            ),
        ];

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function validate_cookies_with_min_length(): void
    {
        $cookies = ['token' => 'valid-token'];
        $parameterSchemas = [
            new Parameter(
                name: 'token',
                in: 'cookie',
                schema: new Schema(type: 'string', minLength: 5),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_min_length_constraint(): void
    {
        $cookies = ['token' => 'abc'];
        $parameterSchemas = [
            new Parameter(
                name: 'token',
                in: 'cookie',
                schema: new Schema(type: 'string', minLength: 5),
            ),
        ];

        $this->expectException(MinLengthError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function validate_cookies_with_max_length(): void
    {
        $cookies = ['token' => 'abc'];
        $parameterSchemas = [
            new Parameter(
                name: 'token',
                in: 'cookie',
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_max_length_constraint(): void
    {
        $cookies = ['token' => 'very-long-token-value'];
        $parameterSchemas = [
            new Parameter(
                name: 'token',
                in: 'cookie',
                schema: new Schema(type: 'string', maxLength: 10),
            ),
        ];

        $this->expectException(MaxLengthError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function validate_cookies_with_pattern(): void
    {
        $cookies = ['code' => 'ABC123'];
        $parameterSchemas = [
            new Parameter(
                name: 'code',
                in: 'cookie',
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_pattern_constraint(): void
    {
        $cookies = ['code' => 'invalid'];
        $parameterSchemas = [
            new Parameter(
                name: 'code',
                in: 'cookie',
                schema: new Schema(type: 'string', pattern: '/^ABC[0-9]{3}$/'),
            ),
        ];

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate($cookies, $parameterSchemas);
    }

    #[Test]
    public function skip_missing_optional_cookies(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'optional',
                in: 'cookie',
                required: false,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_non_cookie_parameters(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'query',
                in: 'query',
                required: true,
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_cookies_multiple_cookies(): void
    {
        $cookies = [
            'session' => 'abc123',
            'user' => 'john',
            'token' => 'xyz789',
        ];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'user',
                in: 'cookie',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            new Parameter(
                name: 'token',
                in: 'cookie',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function parse_cookies_with_whitespace(): void
    {
        $result = $this->validator->parseCookies('name=value ;  name2=value2  ; name3=value3');

        $this->assertSame(['name' => 'value', 'name2' => 'value2', 'name3' => 'value3'], $result);
    }

    #[Test]
    public function parse_cookies_with_special_characters(): void
    {
        $result = $this->validator->parseCookies('session=abc%20123; user=john%20doe');

        $this->assertSame(['session' => 'abc%20123', 'user' => 'john%20doe'], $result);
    }

    #[Test]
    public function parse_cookies_single_pair(): void
    {
        $result = $this->validator->parseCookies('name=value');

        $this->assertSame(['name' => 'value'], $result);
    }

    #[Test]
    public function parse_cookies_with_equals_in_value(): void
    {
        $result = $this->validator->parseCookies('name=value=test');

        $this->assertSame(['name' => 'value=test'], $result);
    }

    #[Test]
    public function parse_cookies_malformed_pairs(): void
    {
        $result = $this->validator->parseCookies('name=value;malformed;name2=value2');

        $this->assertSame(['name' => 'value', 'name2' => 'value2'], $result);
    }

    #[Test]
    public function parse_cookies_whitespace_only(): void
    {
        $result = $this->validator->parseCookies('   ');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_cookies_semicolons_only(): void
    {
        $result = $this->validator->parseCookies(';;;');

        $this->assertSame([], $result);
    }
}
