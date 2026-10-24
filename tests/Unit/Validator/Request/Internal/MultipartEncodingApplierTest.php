<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Validator\Exception\MissingPartHeaderError;
use Duyler\OpenApi\Validator\Exception\PartContentTypeMismatchError;
use Duyler\OpenApi\Validator\Request\Internal\MultipartEncodingApplier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class MultipartEncodingApplierTest extends TestCase
{
    private const string OUTER_BOUNDARY = 'outer';

    private const string INNER_BOUNDARY = 'inner';

    private const string INNERMOST_BOUNDARY = 'innermost';

    #[Test]
    public function matching_nested_content_types_pass_without_errors(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: application/xml', '<note/>'),
                    $this->part('Content-Type: text/plain', 'hello'),
                    $this->part('Content-Type: image/png', 'png-bytes'),
                ),
            ),
            prefixEncoding: $this->fixturePrefixEncoding(),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function mismatched_nested_content_type_throws_part_content_type_mismatch_error(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part('Content-Type: application/json', '{"unexpected":true}'),
                        $this->part('Content-Type: text/plain', 'hello'),
                        $this->part('Content-Type: image/png', 'png-bytes'),
                    ),
                ),
                prefixEncoding: $this->fixturePrefixEncoding(),
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('prefixEncoding', $error->keyword());
            $this->assertSame('application/xml', $error->params()['expected']);
            $this->assertSame('application/json', $error->params()['actual']);
            $this->assertSame(0, $error->params()['partIndex']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the nested XML part carrying application/json');
    }

    #[Test]
    public function missing_encoding_fields_exit_early_without_splitting(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToBody('not a multipart body', 'multipart/mixed; boundary=' . self::OUTER_BOUNDARY);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nesting_deeper_than_one_level_is_passed_through_without_errors(): void
    {
        $applier = new MultipartEncodingApplier();

        $deeperEncoding = new Encoding(
            prefixEncoding: [
                new Encoding(contentType: 'application/x-never-applied'),
            ],
        );

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part(
                        'Content-Type: multipart/mixed; boundary=' . self::INNERMOST_BOUNDARY,
                        $this->body(
                            self::INNERMOST_BOUNDARY,
                            $this->part('Content-Type: application/json', '{"deep":true}'),
                        ),
                    ),
                    $this->part('Content-Type: text/plain', 'hello'),
                    $this->part('Content-Type: image/png', 'png-bytes'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [$deeperEncoding, new Encoding(), new Encoding()],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function missing_required_nested_header_throws_missing_part_header_error(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part('Content-Type: application/xml', '<note/>'),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        prefixEncoding: [
                            new Encoding(headers: new Headers(['X-Trace-Id' => new Header(required: true)])),
                        ],
                    ),
                ],
            );
        } catch (MissingPartHeaderError $error) {
            $this->assertSame('required', $error->keyword());
            $this->assertSame('X-Trace-Id', $error->params()['property']);
            $this->assertSame(0, $error->params()['partIndex']);

            return;
        }

        $this->fail('Expected MissingPartHeaderError for the nested part without X-Trace-Id');
    }

    #[Test]
    public function present_required_nested_header_passes(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part("Content-Type: application/xml\r\nX-Trace-Id: abc", '<note/>'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [
                        new Encoding(headers: new Headers(['X-Trace-Id' => new Header(required: true)])),
                    ],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function content_type_list_matches_any_list_entry(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: text/xml', '<note/>'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [new Encoding(contentType: ['application/xml', 'text/xml'])],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function wildcard_content_type_matches_subtype(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: application/xml', '<note/>'),
                    $this->part('Content-Type: text/plain', 'hello'),
                    $this->part('Content-Type: image/png', 'png-bytes'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [
                        new Encoding(),
                        new Encoding(),
                        new Encoding(contentType: 'image/*'),
                    ],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function wildcard_all_content_type_matches_any_part(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: application/xml', '<note/>'),
                    $this->part('Content-Type: text/plain', 'hello'),
                    $this->part('Content-Type: image/png', 'png-bytes'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [
                        new Encoding(contentType: '*/*'),
                        new Encoding(contentType: '*/*'),
                        new Encoding(contentType: '*/*'),
                    ],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function wildcard_content_type_mismatch_throws_error(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part('Content-Type: text/plain', 'not an image'),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        prefixEncoding: [new Encoding(contentType: 'image/*')],
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('image/*', $error->params()['expected']);
            $this->assertSame('text/plain', $error->params()['actual']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the wildcard image/* expectation');
    }

    #[Test]
    public function content_type_list_mismatch_reports_all_expected_entries(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part('Content-Type: application/json', '{"unexpected":true}'),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        prefixEncoding: [new Encoding(contentType: ['application/xml', 'text/xml'])],
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('application/xml, text/xml', $error->params()['expected']);
            $this->assertSame('application/json', $error->params()['actual']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the list content type expectation');
    }

    #[Test]
    public function item_encoding_applies_to_parts_beyond_prefix(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: application/xml', '<note/>'),
                    $this->part('Content-Type: text/plain', 'first'),
                    $this->part('Content-Type: text/plain', 'second'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [new Encoding(contentType: 'application/xml')],
                    itemEncoding: new Encoding(contentType: 'text/plain'),
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function item_encoding_mismatch_throws_error_with_item_keyword(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part('Content-Type: application/xml', '<note/>'),
                        $this->part('Content-Type: application/json', '{"unexpected":true}'),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        prefixEncoding: [new Encoding(contentType: 'application/xml')],
                        itemEncoding: new Encoding(contentType: 'text/plain'),
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('itemEncoding', $error->keyword());
            $this->assertSame('text/plain', $error->params()['expected']);
            $this->assertSame('application/json', $error->params()['actual']);
            $this->assertSame(1, $error->params()['partIndex']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the item-encoded part');
    }

    #[Test]
    public function encoding_by_name_applies_to_named_nested_part(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part(
                        "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: application/xml",
                        '<note/>',
                    ),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    encoding: ['doc' => new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function encoding_by_name_mismatch_throws_error_with_encoding_keyword(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part(
                            "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: text/plain",
                            'not xml',
                        ),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        encoding: ['doc' => new Encoding(contentType: 'application/xml')],
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('encoding', $error->keyword());

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the named part with a mismatching content type');
    }

    #[Test]
    public function encoding_by_name_resolved_when_filename_precedes_name(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: $this->topLevelParts(
                    innerBody: $this->body(
                        self::INNER_BOUNDARY,
                        $this->part(
                            "Content-Disposition: form-data; filename=\"report.pdf\"; name=\"doc\"\r\n"
                            . 'Content-Type: application/xml',
                            '<note/>',
                        ),
                    ),
                ),
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        encoding: ['doc' => new Encoding(contentType: 'text/plain')],
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('encoding', $error->keyword());
            $this->assertSame('text/plain', $error->params()['expected']);
            $this->assertSame('application/xml', $error->params()['actual']);
            $this->assertSame(0, $error->params()['partIndex']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the part resolved by name despite preceding filename');
    }

    #[Test]
    public function content_type_in_encoding_headers_is_ignored(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: application/xml', '<note/>'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [
                        new Encoding(headers: new Headers(['Content-Type' => new Header(required: true)])),
                    ],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function part_without_content_type_header_skips_content_type_assertion(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Transfer-Encoding: binary', 'opaque payload'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function content_type_matching_is_case_insensitive(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: APPLICATION/XML; charset=utf-8', '<note/>'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_multipart_part_with_nested_encoding_fields_is_skipped(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: [
                $this->part('Content-Type: text/plain', 'plain value'),
                $this->part('Content-Type: application/json', '[1,2]'),
            ],
            prefixEncoding: [
                new Encoding(
                    contentType: 'text/plain',
                    prefixEncoding: [new Encoding(contentType: 'application/xml')],
                ),
                new Encoding(),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nested_content_type_falls_back_to_the_encoding_declaration(): void
    {
        $applier = new MultipartEncodingApplier();

        try {
            $applier->applyToParts(
                parts: [
                    $this->part('Content-Type: application/json', '[1,2]'),
                    $this->part(
                        'Content-Transfer-Encoding: binary',
                        $this->body(
                            self::INNER_BOUNDARY,
                            $this->part('Content-Type: application/json', '{"unexpected":true}'),
                        ),
                    ),
                ],
                prefixEncoding: [
                    new Encoding(),
                    new Encoding(
                        contentType: 'multipart/mixed',
                        prefixEncoding: [new Encoding(contentType: 'application/xml')],
                    ),
                ],
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('application/xml', $error->params()['expected']);
            $this->assertSame('application/json', $error->params()['actual']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the nested body split via the encoding content type');
    }

    #[Test]
    public function non_multipart_encoding_fallback_is_skipped(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: [
                $this->part('Content-Transfer-Encoding: binary', 'opaque value'),
                $this->part('Content-Type: application/json', '[1,2]'),
            ],
            prefixEncoding: [
                new Encoding(
                    contentType: 'text/plain',
                    prefixEncoding: [new Encoding(contentType: 'application/xml')],
                ),
                new Encoding(),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function empty_content_type_list_declares_no_expectation(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: text/plain', 'any value'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [new Encoding(contentType: [])],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function named_part_without_content_disposition_is_skipped(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Type: text/plain', 'no disposition'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    encoding: ['doc' => new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function disposition_without_name_parameter_is_skipped(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part('Content-Disposition: form-data', 'unnamed part'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    encoding: ['doc' => new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function line_feed_only_part_headers_are_parsed(): void
    {
        $applier = new MultipartEncodingApplier();

        $applier->applyToParts(
            parts: $this->topLevelParts(
                innerBody: $this->body(
                    self::INNER_BOUNDARY,
                    $this->part("Content-Disposition: form-data; name=\"doc\"\nContent-Type: application/xml", '<note/>'),
                ),
            ),
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    encoding: ['doc' => new Encoding(contentType: 'application/xml')],
                ),
            ],
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_to_body_splits_the_top_level_body_and_applies_encoding(): void
    {
        $applier = new MultipartEncodingApplier();

        $innerBody = $this->body(
            self::INNER_BOUNDARY,
            $this->part('Content-Type: application/xml', '<note/>'),
            $this->part('Content-Type: text/plain', 'hello'),
            $this->part('Content-Type: image/png', 'png-bytes'),
        );

        $topBody = $this->body(
            self::OUTER_BOUNDARY,
            $this->part('Content-Type: application/json', '[1,2]'),
            $this->part(
                'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY,
                $innerBody,
            ),
        );

        $applier->applyToBody(
            body: $topBody,
            fullContentType: 'multipart/mixed; boundary=' . self::OUTER_BOUNDARY,
            prefixEncoding: $this->fixturePrefixEncoding(),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_to_body_applies_item_encoding_without_prefix_encoding(): void
    {
        $applier = new MultipartEncodingApplier();

        $topBody = $this->body(
            self::OUTER_BOUNDARY,
            $this->part('Content-Type: application/xml', '<note/>'),
            $this->part('Content-Type: application/json', '{"unexpected":true}'),
        );

        try {
            $applier->applyToBody(
                body: $topBody,
                fullContentType: 'multipart/mixed; boundary=' . self::OUTER_BOUNDARY,
                itemEncoding: new Encoding(contentType: 'text/plain'),
            );
        } catch (PartContentTypeMismatchError $error) {
            $this->assertSame('itemEncoding', $error->keyword());
            $this->assertSame('text/plain', $error->params()['expected']);
            $this->assertSame('application/xml', $error->params()['actual']);
            $this->assertSame(0, $error->params()['partIndex']);

            return;
        }

        $this->fail('Expected PartContentTypeMismatchError for the top-level item-encoded part');
    }

    /**
     * @return list<array{headers: string, content: string}>
     */
    private function topLevelParts(string $innerBody): array
    {
        return [
            $this->part('Content-Type: application/json', '[1,2]'),
            $this->part(
                'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY,
                $innerBody,
            ),
        ];
    }

    /**
     * @return list<Encoding>
     */
    private function fixturePrefixEncoding(): array
    {
        return [
            new Encoding(),
            new Encoding(
                contentType: 'multipart/mixed',
                prefixEncoding: [
                    new Encoding(contentType: 'application/xml'),
                    new Encoding(),
                    new Encoding(contentType: 'image/png'),
                ],
            ),
        ];
    }

    /**
     * @param array{headers: string, content: string} ...$parts
     */
    private function body(string $boundary, array ...$parts): string
    {
        $body = '';

        foreach ($parts as $part) {
            $body .= sprintf(
                "--%s\r\n%s\r\n\r\n%s\r\n",
                $boundary,
                $part['headers'],
                $part['content'],
            );
        }

        return $body . "--{$boundary}--";
    }

    /**
     * @return array{headers: string, content: string}
     */
    private function part(string $headers, string $content): array
    {
        return ['headers' => $headers, 'content' => $content];
    }
}
