<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
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

        $this->validator = new CookieValidator($schemaValidator, $deserializer);
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
}
