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
    public function first_content_instance_wins_while_every_instance_stays_addressable(): void
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

        $first = $context->contentResult();

        $this->assertNotNull($first);
        $this->assertSame(['a' => 1], $first->decodedValue());
        $this->assertNotSame(['b' => 2], $first->decodedValue());

        $results = $context->contentResults();

        $this->assertSame(['/0', '/1'], array_keys($results));
        $this->assertSame($first, $results['/0']);
        $this->assertSame(['a' => 1], $context->contentResultAtPath('/0')->decodedValue());
        $this->assertSame(['b' => 2], $context->contentResultAtPath('/1')->decodedValue());
        $this->assertNull($context->contentResultAtPath('/2'));
    }

    #[Test]
    public function object_properties_produce_one_addressable_result_per_property(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            type: 'object',
            properties: [
                'first' => new Schema(
                    type: 'string',
                    contentEncoding: 'base64',
                    contentMediaType: 'application/json',
                ),
                'second' => new Schema(
                    type: 'string',
                    contentEncoding: 'base64',
                    contentMediaType: 'application/json',
                ),
            ],
        );
        $context = $this->createContext();

        $dispatcher->validate(
            ['first' => base64_encode('{"a":1}'), 'second' => base64_encode('{"b":2}')],
            $schema,
            $context,
        );

        $this->assertSame(
            ['first' => ['a' => 1], 'second' => ['b' => 2]],
            [
                'first' => $context->contentResultAtPath('/first')->decodedValue(),
                'second' => $context->contentResultAtPath('/second')->decodedValue(),
            ],
        );
        $this->assertSame(['/first', '/second'], array_keys($context->contentResults()));
    }

    #[Test]
    public function nested_instances_are_addressable_by_their_full_data_path(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'payload' => new Schema(
                        type: 'string',
                        contentEncoding: 'base64',
                        contentMediaType: 'application/json',
                    ),
                ],
            ),
        );
        $context = $this->createContext();

        $dispatcher->validate(
            [['payload' => base64_encode('{"a":1}')], ['payload' => base64_encode('{"b":2}')]],
            $schema,
            $context,
        );

        $this->assertSame(['/0/payload', '/1/payload'], array_keys($context->contentResults()));
        $this->assertSame(['a' => 1], $context->contentResultAtPath('/0/payload')->decodedValue());
        $this->assertSame(['b' => 2], $context->contentResultAtPath('/1/payload')->decodedValue());
    }

    #[Test]
    public function deferred_results_compute_after_the_run_once_and_stay_memoized(): void
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

        $dispatcher->validate([base64_encode('{"a":1}'), base64_encode('{"b":2}')], $schema, $context);

        $this->assertSame(0, $decoder->calls);

        $results = $context->contentResults();

        $this->assertSame($results, $context->contentResults());
        $this->assertCount(2, $results);

        $results['/0']->decodedValue();
        $results['/1']->decodedValue();

        $afterFirstRead = $decoder->calls;

        $this->assertGreaterThan(0, $afterFirstRead);

        $dispatcher->validate([base64_encode('{"a":1}'), base64_encode('{"b":2}')], $schema, $context);

        $this->assertCount(2, $context->contentResults());
        $this->assertSame($results['/0'], $context->contentResultAtPath('/0'));

        $results['/0']->decodedValue();
        $results['/1']->decodedValue();
        $results['/0']->schemaErrors();
        $results['/1']->isValid();

        $this->assertSame($afterFirstRead, $decoder->calls);
    }

    #[Test]
    public function failed_one_of_branch_keeps_its_content_result_out_of_the_list(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $items = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
        );
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 100, contentEncoding: 'base64', contentMediaType: 'application/json'),
                $items,
            ],
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"a":1}'), $schema, $context);

        $results = $context->contentResults();

        $this->assertCount(1, $results);
        $this->assertSame(['a' => 1], $context->contentResultAtPath('/')->decodedValue());
        $this->assertSame($context->contentResult(), $context->contentResultAtPath('/'));
    }

    #[Test]
    public function any_of_branches_sharing_a_path_keep_the_first_merged_branch_result(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            anyOf: [
                new Schema(
                    type: 'string',
                    contentEncoding: 'base64',
                    contentMediaType: 'application/json',
                    contentSchema: new Schema(type: 'object', required: ['a']),
                ),
                new Schema(
                    type: 'string',
                    contentEncoding: 'base64',
                    contentMediaType: 'application/json',
                    contentSchema: new Schema(type: 'object', required: ['b']),
                ),
            ],
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"a":1}'), $schema, $context);

        $results = $context->contentResults();

        $this->assertSame(['/'], array_keys($results));
        $this->assertSame(['a' => 1], $results['/']->decodedValue());
        $this->assertSame([], $results['/']->schemaErrors());
        $this->assertSame($context->contentResult(), $results['/']);
    }

    #[Test]
    public function then_branch_result_joins_the_list_when_if_holds(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            if: new Schema(type: 'string', minLength: 1),
            then: new Schema(
                type: 'string',
                contentEncoding: 'base64',
                contentMediaType: 'application/json',
            ),
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"a":1}'), $schema, $context);

        $results = $context->contentResults();

        $this->assertSame(['/'], array_keys($results));
        $this->assertSame(['a' => 1], $results['/']->decodedValue());
        $this->assertSame($context->contentResult(), $results['/']);
    }

    #[Test]
    public function else_branch_result_joins_the_list_when_if_fails(): void
    {
        $decoder = new CountingContentDecoder();
        $dispatcher = $this->createDispatcher($decoder);
        $schema = new Schema(
            if: new Schema(type: 'integer'),
            else: new Schema(
                type: 'string',
                contentEncoding: 'base64',
                contentMediaType: 'application/json',
            ),
        );
        $context = $this->createContext();

        $dispatcher->validate(base64_encode('{"a":1}'), $schema, $context);

        $results = $context->contentResults();

        $this->assertSame(['/'], array_keys($results));
        $this->assertSame(['a' => 1], $results['/']->decodedValue());
        $this->assertSame($context->contentResult(), $results['/']);
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
