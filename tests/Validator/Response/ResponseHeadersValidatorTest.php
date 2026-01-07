<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
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
}
