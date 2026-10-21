<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
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
        $schemaValidator = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        )->rootStatelessValidator();

        $this->validator = new ResponseHeadersValidator($schemaValidator);
    }

    #[Test]
    public function large_array_items_rejected(): void
    {
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
