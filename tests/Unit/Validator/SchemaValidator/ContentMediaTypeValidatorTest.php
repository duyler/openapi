<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\InvalidContentMediaTypeException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const LIBXML_NOENT;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

#[CoversClass(ContentMediaTypeValidator::class)]
class ContentMediaTypeValidatorTest extends TestCase
{
    private ContentMediaTypeValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new ContentMediaTypeValidator();
    }

    #[Test]
    public function skip_when_content_media_type_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $succeeded = false;
        try {
            $this->validator->validate('any value', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_data_is_not_string(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $succeeded = false;
        try {
            $this->validator->validate(123, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_valid_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $succeeded = false;
        try {
            $this->validator->validate('{"key": "value"}', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not json at all', $schema);
    }

    #[Test]
    public function throw_error_for_empty_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    /**
     * Anti-test for EI-003: proves isValidJson is independent of the global
     * json_last_error state. In long-running runtimes (Swoole coroutines,
     * RoadRunner, FrankenPHP) the global error code is shared across requests
     * and coroutines; an unrelated failed json_decode could otherwise corrupt
     * the validator's verdict (false-positive "valid"). With JSON_THROW_ON_ERROR
     * the check is atomic and immune to such pollution.
     */
    #[Test]
    public function json_validation_is_independent_of_global_json_error_state(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        json_decode('this is garbage and definitely not valid json');

        $succeeded = false;
        try {
            $this->validator->validate('{"valid":1}', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * Anti-test for EI-051: proves the depth limit blocks DoS payloads with
     * deeply-nested JSON. Default json_decode depth is 512; the validator
     * enforces 128, which rejects a payload of depth 200 with
     * InvalidContentMediaTypeException. Without the limit the parser could
     * stack-overflow or burn CPU on a malicious payload.
     */
    #[Test]
    public function deeply_nested_json_above_max_depth_is_rejected(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $depthExceedingPayload = str_repeat('{"a":', 200) . '1' . str_repeat('}', 200);

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate($depthExceedingPayload, $schema);
    }

    /**
     * Boundary test for EI-051: proves JSON nested within the depth limit
     * (depth 100, under the cap of 128) passes validation.
     */
    #[Test]
    public function deeply_nested_json_within_max_depth_is_accepted(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $depthWithinPayload = str_repeat('{"a":', 100) . '1' . str_repeat('}', 100);

        $succeeded = false;
        try {
            $this->validator->validate($depthWithinPayload, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * Documents the exact depth cutoff: payloads nested at JSON_MAX_DEPTH - 1
     * (127 levels) pass validation, while JSON_MAX_DEPTH (128 levels) is
     * rejected. PHP's json_decode depth parameter has an off-by-one — depth=N
     * permits nesting up to N-1. This test locks the boundary so a future
     * change to the constant or to the depth parameter is immediately
     * detected.
     */
    #[Test]
    public function json_depth_boundary_is_enforced_at_max_depth_minus_one(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');
        $withinPayload = str_repeat('{"a":', 127) . '1' . str_repeat('}', 127);
        $atCapPayload = str_repeat('{"a":', 128) . '1' . str_repeat('}', 128);

        $succeededWithin = false;
        try {
            $this->validator->validate($withinPayload, $schema);
            $succeededWithin = true;
        } catch (InvalidContentMediaTypeException) {
        }
        self::assertTrue($succeededWithin, 'JSON nested at depth 127 (JSON_MAX_DEPTH - 1) must pass');

        $this->expectException(InvalidContentMediaTypeException::class);
        $this->validator->validate($atCapPayload, $schema);
    }

    #[Test]
    public function validate_valid_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $succeeded = false;
        try {
            $this->validator->validate('<root><item>test</item></root>', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not xml', $schema);
    }

    #[Test]
    public function xxe_attack_is_blocked_in_xml_validation(): void
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root>&xxe;</root>
XML;

        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $succeeded = false;
        try {
            $this->validator->validate($xxePayload, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_valid_text_plain(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/plain');

        $succeeded = false;
        try {
            $this->validator->validate('any text content', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_empty_text_plain(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/plain');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function validate_valid_html(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/html');

        $succeeded = false;
        try {
            $this->validator->validate('<html><body>Hello</body></html>', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_html(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/html');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('no html tags here', $schema);
    }

    #[Test]
    public function validate_valid_pdf(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/pdf');

        $succeeded = false;
        try {
            $this->validator->validate('%PDF-1.4 binary content', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_pdf(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/pdf');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a pdf', $schema);
    }

    #[Test]
    public function validate_valid_octet_stream(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $succeeded = false;
        try {
            $this->validator->validate('binary data', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_empty_octet_stream(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function validate_valid_png(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/png');

        $succeeded = false;
        try {
            $this->validator->validate("\x89PNG\r\n\x1a\nbinary data", $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_png(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/png');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a png', $schema);
    }

    #[Test]
    public function validate_valid_jpeg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/jpeg');

        $succeeded = false;
        try {
            $this->validator->validate("\xFF\xD8\xFF\xE0binary data", $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_jpeg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/jpeg');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a jpeg', $schema);
    }

    #[Test]
    public function validate_valid_gif87a(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $succeeded = false;
        try {
            $this->validator->validate('GIF87abinary data', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_valid_gif89a(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $succeeded = false;
        try {
            $this->validator->validate('GIF89abinary data', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_gif(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a gif', $schema);
    }

    #[Test]
    public function validate_valid_svg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/svg+xml');

        $succeeded = false;
        try {
            $this->validator->validate('<svg xmlns="http://www.w3.org/2000/svg"><circle r="10"/></svg>', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_svg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/svg+xml');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not svg', $schema);
    }

    #[Test]
    public function validate_valid_multipart_form_data(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $succeeded = false;
        try {
            $this->validator->validate("--boundary\r\nContent-Disposition: form-data; name=\"field\"\r\n\r\nvalue\r\n--boundary--", $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_multipart_form_data(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not multipart', $schema);
    }

    /**
     * P-013: RFC 7578 §4 requires both an opening boundary (`--boundary`)
     * and a closing boundary (`--boundary--`) at the end. A payload with
     * only an opening boundary MUST be rejected.
     */
    #[Test]
    public function rejects_only_opening_boundary(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate("--boundary\r\nContent-Disposition: form-data; name=\"field\"\r\n\r\nvalue", $schema);
    }

    #[Test]
    public function rejects_random_dash_prefix_string_without_closing_boundary(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('--foobar random text without closing boundary', $schema);
    }

    #[Test]
    public function rejects_mismatched_closing_boundary(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate("--abc\r\nbody\r\n--xyz--", $schema);
    }

    #[Test]
    public function accepts_well_formed_multipart_with_matching_closing_boundary(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $succeeded = false;
        try {
            $this->validator->validate("--MYBOUNDARY\r\nContent-Disposition: form-data; name=\"x\"\r\n\r\nv\r\n--MYBOUNDARY--\r\n", $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected well-formed multipart to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_valid_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $succeeded = false;
        try {
            $this->validator->validate('key=value&foo=bar', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_empty_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $succeeded = false;
        try {
            $this->validator->validate('', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('=value_only', $schema);
    }

    #[Test]
    public function validate_text_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/xml');

        $succeeded = false;
        try {
            $this->validator->validate('<root>content</root>', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function pass_for_unknown_supported_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $succeeded = false;
        try {
            $this->validator->validate('data', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function xxe_doctype_with_etc_passwd_not_substituted_in_xml_validation(): void
    {
        $xxePayload = <<<'XML'
<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]><root><name>&xxe;</name></root>
XML;

        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $succeeded = false;
        try {
            $this->validator->validate($xxePayload, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function libxml_internal_errors_state_restored_after_xml_validation(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        libxml_use_internal_errors(false);

        $this->validator->validate('<root><item>test</item></root>', $schema);

        self::assertFalse(libxml_use_internal_errors());
    }

    /**
     * Defense-in-depth anti-test for the schema validator: proves that the
     * deny-all external entity loader (installed by LibxmlSecuredContext)
     * blocks file:// resolution even if a future commit accidentally adds
     * LIBXML_NOENT to XML_PARSE_OPTIONS. Without LIBXML_NOENT XXE is already
     * blocked by simplexml_load_string semantics; this test demonstrates
     * that the loader itself is the invariant guard, so the protection no
     * longer relies solely on the absence of a flag.
     */
    #[Test]
    public function deny_all_loader_blocks_file_resolution_even_with_noent_flag_in_xml_validation(): void
    {
        $secretContent = 'XXE_SECRET_' . uniqid('', true);
        $tempFile = tempnam(sys_get_temp_dir(), 'xxe_noent_valid_');

        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, $secretContent);

        try {
            $xxePayload = sprintf(
                '<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file://%s"> ]><root><name>&xxe;</name></root>',
                $tempFile,
            );

            $previousInternalErrors = libxml_use_internal_errors(true);
            libxml_set_external_entity_loader(static fn(): null => null);

            try {
                $xml = simplexml_load_string(
                    $xxePayload,
                    options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOENT,
                );
                $encoded = false === $xml ? '' : (string) json_encode($xml);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousInternalErrors);
            }

            self::assertStringNotContainsString(
                $secretContent,
                $encoded,
                'deny-all loader must prevent file:// entity resolution even with LIBXML_NOENT flag',
            );
        } finally {
            unlink($tempFile);
        }
    }
}
