<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
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
}
