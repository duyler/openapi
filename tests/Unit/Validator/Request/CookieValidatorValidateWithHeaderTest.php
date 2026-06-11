<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CookieValidatorValidateWithHeaderTest extends TestCase
{
    private CookieValidator $validator;

    protected function setUp(): void
    {
        $schemaValidator = new SchemaValidator(new ValidatorPool(), BuiltinFormats::instance());
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $this->validator = new CookieValidator(
            schemaValidator: $schemaValidator,
            deserializer: $deserializer,
            coercer: $coercer,
            coercion: false,
        );
    }

    #[Test]
    public function validate_with_header_throws_for_missing_required_cookie(): void
    {
        $param = new Parameter(
            name: 'session',
            in: 'cookie',
            required: true,
            schema: new Schema(type: 'string'),
        );

        $this->expectException(MissingParameterException::class);

        $this->validator->validateWithHeader([], '', [$param]);
    }

    #[Test]
    public function validate_with_header_passes_for_optional_missing_cookie(): void
    {
        $param = new Parameter(
            name: 'session',
            in: 'cookie',
            required: false,
            schema: new Schema(type: 'string'),
        );

        $this->validator->validateWithHeader([], '', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_header_validates_cookie_from_header(): void
    {
        $param = new Parameter(
            name: 'token',
            in: 'cookie',
            required: true,
            schema: new Schema(type: 'string'),
        );

        $this->validator->validateWithHeader([], 'token=abc123', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_header_validates_cookie_from_data(): void
    {
        $param = new Parameter(
            name: 'theme',
            in: 'cookie',
            required: true,
            schema: new Schema(type: 'string'),
        );

        $this->validator->validateWithHeader(['theme' => 'dark'], '', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_header_skips_non_cookie_params(): void
    {
        $param = new Parameter(name: 'id', in: 'query');

        $this->validator->validateWithHeader([], '', [$param]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_header_skips_non_parameter_items(): void
    {
        $this->validator->validateWithHeader([], '', ['not_a_param', 42]);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_header_skips_null_name_params(): void
    {
        $param = new Parameter(in: 'cookie');

        $this->validator->validateWithHeader([], 'test=value', [$param]);

        $this->assertTrue(true);
    }
}
