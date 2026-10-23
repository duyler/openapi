<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Support\CountingContentDecoder;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentAssertionPipeline;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[CoversClass(ContentAssertionPipeline::class)]
class ContentPipelineLazyResultTest extends TestCase
{
    #[Test]
    public function main_pass_stays_decode_free_and_result_computes_on_demand(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"b":1}'), $schema, $context);

        $this->assertSame(0, $decoder->calls);

        $result = $context->contentResult();

        $this->assertNotNull($result);
        $this->assertTrue($result->isMediaTypeValid());
        $this->assertSame(['b' => 1], $result->decodedValue());
        $this->assertSame('required', $result->schemaErrors()[0]->keyword());
        $this->assertFalse($result->isValid());
        $this->assertGreaterThan(0, $decoder->calls);
    }

    #[Test]
    public function valid_payload_exposes_decoded_value_without_affecting_outcome(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"a":1}'), $schema, $context);

        $this->assertSame(0, $decoder->calls);

        $result = $context->contentResult();

        $this->assertNotNull($result);
        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertSame([], $result->schemaErrors());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function encoding_only_schema_attaches_result_from_the_encoding_validator(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(type: 'string', contentEncoding: 'base64');
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('plain payload'), $schema, $context);

        $this->assertSame(0, $decoder->calls);

        $result = $context->contentResult();

        $this->assertNotNull($result);
        $this->assertSame('plain payload', $result->decodedValue());
        $this->assertTrue($result->isMediaTypeValid());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function first_content_instance_wins_when_two_strings_carry_content_keywords(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'string',
                contentEncoding: 'base64',
                contentMediaType: 'application/json',
            ),
        );
        $context = $this->createContext();

        $dispatcher->validate(
            [base64_encode('{"a":1}'), base64_encode('{"b":2}')],
            $schema,
            $context,
        );

        $result = $context->contentResult();

        $this->assertNotNull($result);
        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertNotSame(['b' => 2], $result->decodedValue());
    }

    private function createDispatcher(CountingContentDecoder $decoder): StatelessValidatorDispatcher
    {
        $dependencies = new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            contentAssertionsEnabled: false,
            contentDecoder: $decoder,
        );

        return new StatelessValidatorDispatcher($dependencies->createFullList(), new ValidatorPool());
    }

    private function createContext(): ValidationContext
    {
        return ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
    }
}
