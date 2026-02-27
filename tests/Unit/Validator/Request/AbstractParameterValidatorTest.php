<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractParameterValidatorTest extends TestCase
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
    public function skip_missing_optional_parameter(): void
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
    public function skip_parameter_with_different_location(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: 'authorization',
                in: 'header',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_parameter_with_null_name(): void
    {
        $cookies = ['session' => 'abc123'];
        $parameterSchemas = [
            new Parameter(
                name: null,
                in: 'cookie',
                required: true,
                schema: new Schema(type: 'string'),
            ),
        ];

        $this->validator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }
}
