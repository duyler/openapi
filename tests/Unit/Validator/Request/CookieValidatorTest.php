<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
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

    #[Test]
    public function parse_cookie_style_simple_value(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('session=abc123', $parameter);

        $this->assertSame('abc123', $result);
    }

    #[Test]
    public function parse_cookie_style_with_explode_false(): void
    {
        $parameter = new Parameter(
            name: 'ids',
            in: 'cookie',
            style: 'cookie',
            explode: false,
            schema: new Schema(type: 'array'),
        );

        $result = $this->validator->parseCookieStyle('ids=1,2,3', $parameter);

        $this->assertSame(['1', '2', '3'], $result);
    }

    #[Test]
    public function parse_cookie_style_with_explode_false_string_value(): void
    {
        $parameter = new Parameter(
            name: 'message',
            in: 'cookie',
            style: 'cookie',
            explode: false,
            schema: new Schema(type: 'string'),
        );

        $result = $this->validator->parseCookieStyle('message=Hello, world!', $parameter);

        $this->assertSame('Hello, world!', $result);
    }

    #[Test]
    public function parse_cookie_style_with_explode_true(): void
    {
        $parameter = new Parameter(
            name: 'tags',
            in: 'cookie',
            style: 'cookie',
            explode: true,
        );

        $result = $this->validator->parseCookieStyle('tags=a;tags=b;tags=c', $parameter);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function parse_cookie_style_url_encoded(): void
    {
        $parameter = new Parameter(
            name: 'data',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('data=hello%20world', $parameter);

        $this->assertSame('hello world', $result);
    }

    #[Test]
    public function parse_cookie_style_missing_parameter(): void
    {
        $parameter = new Parameter(
            name: 'missing',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('other=value', $parameter);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_cookie_style_multiple_cookies(): void
    {
        $parameter = new Parameter(
            name: 'user',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('session=abc;user=john;token=xyz', $parameter);

        $this->assertSame('john', $result);
    }

    #[Test]
    public function form_style_remains_backward_compatible(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'form',
        );

        $result = $this->validator->parseCookieStyle('session=abc123', $parameter);

        $this->assertSame('abc123', $result);
    }

    #[Test]
    public function parse_cookie_style_empty_header(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('', $parameter);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_cookie_style_whitespace_only_header(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('   ', $parameter);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_cookie_style_default_is_form(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('session=abc123', $parameter);

        $this->assertSame('abc123', $result);
    }

    #[Test]
    public function parse_cookie_style_explode_url_encoded(): void
    {
        $parameter = new Parameter(
            name: 'tags',
            in: 'cookie',
            style: 'cookie',
            explode: true,
        );

        $result = $this->validator->parseCookieStyle('tags=hello%20world;tags=foo%2Bbar', $parameter);

        $this->assertSame(['hello world', 'foo+bar'], $result);
    }

    #[Test]
    public function parse_cookie_style_explode_mixed_cookies(): void
    {
        $parameter = new Parameter(
            name: 'tags',
            in: 'cookie',
            style: 'cookie',
            explode: true,
        );

        $result = $this->validator->parseCookieStyle('session=abc;tags=x;user=john;tags=y', $parameter);

        $this->assertSame(['x', 'y'], $result);
    }

    #[Test]
    public function parse_cookie_style_invalid_style_throws_exception(): void
    {
        $parameter = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'matrix',
        );

        $this->expectException(InvalidParameterException::class);

        $this->validator->parseCookieStyle('session=abc123', $parameter);
    }

    #[Test]
    public function parse_cookie_style_comma_separated_with_spaces(): void
    {
        $parameter = new Parameter(
            name: 'tags',
            in: 'cookie',
            style: 'cookie',
            explode: false,
            schema: new Schema(type: 'array'),
        );

        $result = $this->validator->parseCookieStyle('tags=a, b, c', $parameter);

        $this->assertSame(['a', ' b', ' c'], $result);
    }

    #[Test]
    public function parse_cookie_style_explode_single_value(): void
    {
        $parameter = new Parameter(
            name: 'tag',
            in: 'cookie',
            style: 'cookie',
            explode: true,
        );

        $result = $this->validator->parseCookieStyle('tag=onlyone', $parameter);

        $this->assertSame('onlyone', $result);
    }

    #[Test]
    public function parse_cookie_style_special_characters(): void
    {
        $parameter = new Parameter(
            name: 'data',
            in: 'cookie',
            style: 'cookie',
        );

        $result = $this->validator->parseCookieStyle('data=%7B%22key%22%3A%22value%22%7D', $parameter);

        $this->assertSame('{"key":"value"}', $result);
    }

    #[Test]
    public function validate_with_header_style_cookie(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                style: 'cookie',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'session=abc123', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_explode_true(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'tags',
                in: 'cookie',
                style: 'cookie',
                explode: true,
                schema: new Schema(type: 'array', items: new Schema(type: 'string')),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'tags=a;tags=b;tags=c', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_url_encoded(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'data',
                in: 'cookie',
                style: 'cookie',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'data=hello%20world', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_missing_required(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                style: 'cookie',
                required: true,
            ),
        ];

        $this->expectException(MissingParameterException::class);

        $this->validator->validateWithHeader($cookies, '', $parameterSchemas);
    }

    #[Test]
    public function validate_with_header_form_style_backward_compatible(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                style: 'form',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'session=abc123', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_comma_separated_array(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'ids',
                in: 'cookie',
                style: 'cookie',
                schema: new Schema(type: 'array', items: new Schema(type: 'string')),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'ids=1,2,3', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_uses_parsed_cookies_for_form_style(): void
    {
        $cookies = ['session' => 'from_parsed'];
        $parameterSchemas = [
            new Parameter(
                name: 'session',
                in: 'cookie',
                style: 'form',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'session=from_header', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_form_style_url_decoded(): void
    {
        $cookies = [];
        $parameterSchemas = [
            new Parameter(
                name: 'data',
                in: 'cookie',
                style: 'form',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, 'data=hello%20world', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_header_fallback_url_decoded(): void
    {
        $cookies = ['data' => 'hello%20world'];
        $parameterSchemas = [
            new Parameter(
                name: 'data',
                in: 'cookie',
                style: 'form',
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validateWithHeader($cookies, '', $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }
}
