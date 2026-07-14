<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function str_repeat;

/** @internal */
final class ResponseHeadersValidatorExplodeLimitTest extends TestCase
{
    private ResponseHeadersValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());

        $this->validator = new ResponseHeadersValidator($schemaValidator);
    }

    #[Test]
    public function large_array_items_rejected(): void
    {
        // MAX_HEADER_ARRAY_ITEMS = 1000 → 1000 commas splits into 1001 items.
        $value = 'a' . str_repeat(',a', 1000);

        $headers = ['X-Tags' => $value];
        $headerSchemas = new Headers([
            'X-Tags' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->expectException(InvalidParameterException::class);

        $this->validator->validate($headers, $headerSchemas);
    }

    #[Test]
    public function normal_array_items_accepted(): void
    {
        // 3 items — well under the 1000 cap.
        $headers = ['X-Tags' => 'a,b,c'];
        $headerSchemas = new Headers([
            'X-Tags' => new Header(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ),
        ]);

        $this->validator->validate($headers, $headerSchemas);

        $this->expectNotToPerformAssertions();
    }
}
