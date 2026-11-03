<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Support\CountingContentDecoder;
use Duyler\OpenApi\Validator\ContentDecoderInterface;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DecodedContentTooLargeException;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentAssertionPipeline;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;
use function strtr;

#[CoversClass(ContentAssertionPipeline::class)]
#[CoversClass(DecodedContentTooLargeException::class)]
class ContentAssertionPipelineTest extends TestCase
{
    private const string JSON_WITH_A = '{"a":1}';

    private const string JSON_WITHOUT_A = '{"b":1}';

    #[Test]
    public function assertion_pipeline_accepts_encoded_json_matching_content_schema(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $result = $pipeline->assert(base64_encode(self::JSON_WITH_A), $schema, $context);

        $this->assertTrue($result->isMediaTypeValid());
        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertSame([], $result->schemaErrors());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function assertion_pipeline_rejects_encoded_json_violating_content_schema(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $this->expectException(ValidationException::class);

        $pipeline->assert(base64_encode(self::JSON_WITHOUT_A), $schema, $context);
    }

    #[Test]
    public function assertion_pipeline_validates_plain_json_without_encoding(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $result = $pipeline->assert(self::JSON_WITH_A, $schema, $context);

        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function deferred_pipeline_collects_schema_errors_without_throwing(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $result = $pipeline->defer(base64_encode(self::JSON_WITHOUT_A), $schema, $context);

        $this->assertSame(['b' => 1], $result->decodedValue());
        $this->assertTrue($result->isMediaTypeValid());
        $this->assertCount(1, $result->schemaErrors());
        $this->assertSame('required', $result->schemaErrors()[0]->keyword());
        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function deferred_pipeline_reports_unmatched_required_keyword_error(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $result = $pipeline->defer(self::JSON_WITHOUT_A, $schema, $context);

        $this->assertCount(1, $result->schemaErrors());
        $this->assertSame('required', $result->schemaErrors()[0]->keyword());
    }

    #[Test]
    public function deferred_pipeline_records_failed_decode_without_throwing(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
        );
        $context = $this->createContext();

        $result = $pipeline->defer('not-base64!!!', $schema, $context);

        $this->assertNull($result->decodedValue());
        $this->assertFalse($result->isMediaTypeValid());
        $this->assertSame([], $result->schemaErrors());
    }

    #[Test]
    public function assertion_pipeline_throws_on_failed_decode(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
        );
        $context = $this->createContext();

        $this->expectException(InvalidContentEncodingException::class);

        $pipeline->assert('not-base64!!!', $schema, $context);
    }

    #[Test]
    public function deferred_pipeline_decodes_base64url_tokens(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64url',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();
        $token = strtr(base64_encode(self::JSON_WITH_A), '+/', '-_');

        $result = $pipeline->defer($token, $schema, $context);

        $this->assertSame(['a' => 1], $result->decodedValue());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function deferred_pipeline_pads_base64url_tokens_with_two_missing_characters(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(type: 'string', contentEncoding: 'base64url', contentMediaType: 'text/plain');
        $context = $this->createContext();

        $result = $pipeline->defer('eyJhIjoxfQ', $schema, $context);

        $this->assertSame('{"a":1}', $result->decodedValue());
    }

    #[Test]
    public function deferred_pipeline_pads_base64url_tokens_with_one_missing_character(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(type: 'string', contentEncoding: 'base64url', contentMediaType: 'text/plain');
        $context = $this->createContext();

        $result = $pipeline->defer('MTIzNDU', $schema, $context);

        $this->assertSame('12345', $result->decodedValue());
    }

    #[Test]
    public function deferred_pipeline_treats_identity_encodings_verbatim(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'binary',
            contentMediaType: 'text/plain',
        );
        $context = $this->createContext();

        $result = $pipeline->defer("\x00\xFF binary payload", $schema, $context);

        $this->assertSame("\x00\xFF binary payload", $result->decodedValue());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function skip_media_type_flag_bypasses_media_syntax_check(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object'),
        );
        $context = $this->createContext();

        $result = $pipeline->defer('[1,2]', $schema, $context, skipMediaType: true);

        $this->assertTrue($result->isMediaTypeValid());
        $this->assertCount(1, $result->schemaErrors());
        $this->assertSame('type', $result->schemaErrors()[0]->keyword());
    }

    #[Test]
    public function boolean_false_content_schema_rejects_decoded_value_in_collect_mode(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: false,
        );
        $context = $this->createContext();

        $result = $pipeline->defer(self::JSON_WITH_A, $schema, $context);

        $this->assertCount(1, $result->schemaErrors());
        $this->assertInstanceOf(TypeMismatchError::class, $result->schemaErrors()[0]);
    }

    #[Test]
    public function boolean_false_content_schema_throws_in_assertion_mode(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: false,
        );
        $context = $this->createContext();

        $this->expectException(TypeMismatchError::class);

        $pipeline->assert(self::JSON_WITH_A, $schema, $context);
    }

    #[Test]
    public function boolean_true_content_schema_accepts_any_decoded_value(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: true,
        );
        $context = $this->createContext();

        $result = $pipeline->assert(self::JSON_WITHOUT_A, $schema, $context);

        $this->assertSame([], $result->schemaErrors());
        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function media_syntax_check_skipped_without_content_media_type(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(type: 'string', contentEncoding: 'binary');
        $context = $this->createContext();

        $result = $pipeline->assert('any payload', $schema, $context);

        $this->assertTrue($result->isMediaTypeValid());
        $this->assertSame('any payload', $result->decodedValue());
    }

    #[Test]
    public function assertion_pipeline_checks_decoded_base64url_payload(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64url',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();
        $token = rtrim(strtr(base64_encode(self::JSON_WITHOUT_A), '+/', '-_'), '=');

        $this->expectException(ValidationException::class);

        $pipeline->assert($token, $schema, $context);
    }

    #[Test]
    public function oversized_decoded_payload_throws_domain_exception(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true, maxDecodedContentBytes: 1024);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
        );
        $context = $this->createContext();

        try {
            $pipeline->assert(base64_encode(str_repeat('a', 8192)), $schema, $context);
            $this->fail('Expected DecodedContentTooLargeException');
        } catch (DecodedContentTooLargeException $exception) {
            $this->assertSame(8192, $exception->actualBytes);
            $this->assertSame(1024, $exception->maxBytes);
            $this->assertSame('base64', $exception->encoding);
        }
    }

    #[Test]
    public function oversized_decoded_payload_throws_on_lazy_read(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false, maxDecodedContentBytes: 1024);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
        );
        $context = $this->createContext();

        $result = $pipeline->defer(base64_encode(str_repeat('a', 8192)), $schema, $context);

        $this->expectException(DecodedContentTooLargeException::class);

        $result->decodedValue();
    }

    #[Test]
    public function oversized_identity_payload_throws_without_encoding(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true, maxDecodedContentBytes: 16);
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');
        $context = $this->createContext();

        $this->expectException(DecodedContentTooLargeException::class);
        $this->expectExceptionMessage('Decoded content of 32 bytes exceeds the configured maximum of 16 bytes');

        $pipeline->assert(str_repeat('a', 32), $schema, $context);
    }

    #[Test]
    public function subschema_validation_reuses_the_caller_context(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', extras: ['x-context-marker' => true]),
        );
        $context = $this->createContext();

        $pipeline->assert(self::JSON_WITH_A, $schema, $context);

        $annotations = $context->annotations();
        $this->assertArrayHasKey('x-context-marker', $annotations);
    }

    #[Test]
    public function decode_counter_stays_silent_until_deferred_result_is_read(): void
    {
        $decoder = new CountingContentDecoder();
        $pipeline = $this->createPipeline(contentAssertionsEnabled: false, decoder: $decoder);
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: new Schema(type: 'object', required: ['a']),
        );
        $context = $this->createContext();

        $result = $pipeline->defer(base64_encode(self::JSON_WITHOUT_A), $schema, $context);

        $this->assertSame(0, $decoder->calls);

        $this->assertCount(1, $result->schemaErrors());
        $this->assertSame(1, $decoder->calls);

        $this->assertSame(['b' => 1], $result->decodedValue());
        $this->assertSame(1, $decoder->calls);
    }

    #[Test]
    public function media_syntax_is_checked_per_media_type(): void
    {
        $pipeline = $this->createPipeline(contentAssertionsEnabled: true);
        $context = $this->createContext();

        $cases = $this->mediaSyntaxCases();

        foreach ($cases as $label => [$payload, $mediaType, $expectedValidity]) {
            $schema = new Schema(type: 'string', contentMediaType: $mediaType);
            $result = $pipeline->assert($payload, $schema, $context);

            $this->assertSame($expectedValidity, $result->isMediaTypeValid(), $label);
        }
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    private function mediaSyntaxCases(): array
    {
        return [
            'json_empty' => ['', 'application/json', false],
            'json_valid' => ['{"a":1}', 'application/json', true],
            'json_invalid' => ['{a:1}', 'application/json', false],
            'xml_valid' => ['<root/>', 'application/xml', true],
            'xml_invalid' => ['<root>', 'application/xml', false],
            'xml_empty' => ['', 'application/xml', false],
            'svg_valid' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml', true],
            'text_plain_valid' => ['plain text', 'text/plain', true],
            'text_plain_empty' => ['', 'text/plain', false],
            'html_valid' => ['<p>text</p>', 'text/html', true],
            'html_invalid' => ['plain text', 'text/html', false],
            'html_empty' => ['', 'text/html', false],
            'pdf_valid' => ['%PDF-1.7 rest', 'application/pdf', true],
            'pdf_invalid' => ['not pdf', 'application/pdf', false],
            'octet_stream_valid' => [ "\x00\x01", 'application/octet-stream', true],
            'octet_stream_empty' => ['', 'application/octet-stream', false],
            'png_valid' => ["\x89PNG\r\n", 'image/png', true],
            'png_invalid' => ['NOPE', 'image/png', false],
            'jpeg_valid' => ["\xFF\xD8\xFF rest", 'image/jpeg', true],
            'jpeg_invalid' => ['NOPE', 'image/jpeg', false],
            'gif_valid' => ['GIF89a rest', 'image/gif', true],
            'gif_invalid' => ['NOPE', 'image/gif', false],
            'multipart_valid' => ["--boundary\r\npart\r\n--boundary--\r\n", 'multipart/form-data', true],
            'multipart_invalid' => ['--boundary only', 'multipart/form-data', false],
            'multipart_no_boundary' => ['plain text', 'multipart/form-data', false],
            'url_encoded_valid' => ['a=1&b=2', 'application/x-www-form-urlencoded', true],
            'url_encoded_empty' => ['', 'application/x-www-form-urlencoded', true],
            'url_encoded_too_many_pairs' => ['a=1&' . str_repeat('b=2&', 1000) . 'c=3', 'application/x-www-form-urlencoded', false],
            'unknown_media_type' => ['anything', 'application/x-unknown', false],
        ];
    }

    private function createPipeline(
        bool $contentAssertionsEnabled = false,
        ?ContentDecoderInterface $decoder = null,
        int $maxDecodedContentBytes = ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES,
    ): ContentAssertionPipeline {
        return new ContentAssertionPipeline(
            $this->createDependencies($contentAssertionsEnabled, $decoder, $maxDecodedContentBytes),
        );
    }

    private function createDependencies(
        bool $contentAssertionsEnabled,
        ?ContentDecoderInterface $decoder,
        int $maxDecodedContentBytes,
    ): SchemaValidatorDependencies {
        return new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            contentAssertionsEnabled: $contentAssertionsEnabled,
            contentDecoder: $decoder ?? new CountingContentDecoder(),
            maxDecodedContentBytes: $maxDecodedContentBytes,
        );
    }

    private function createContext(): ValidationContext
    {
        return ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
    }
}
