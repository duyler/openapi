<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MultipartNestingDepthException;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\Internal\MultipartInstanceResolver;
use Duyler\OpenApi\Validator\Request\Internal\MultipartPartNode;
use Duyler\OpenApi\Validator\Request\Internal\PartDisposition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultipartInstanceResolver::class)]
#[CoversClass(MultipartPartNode::class)]
#[CoversClass(PartDisposition::class)]
final class MultipartInstanceResolverTest extends TestCase
{
    private const string INNER_BOUNDARY = 'inner';

    #[Test]
    public function positional_values_decode_json_parts_and_keep_binary_raw(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', items: new Schema());
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => 'Content-Type: application/json', 'content' => '{"author":"a"}' . "\r\n"],
                ['headers' => 'Content-Type: image/png', 'content' => "PNG\r\n"],
                ['headers' => 'Content-Type: text/plain', 'content' => "plain\n"],
            ],
            prefixEncoding: [new Encoding(), new Encoding(), new Encoding()],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['author' => 'a'], $values[0]);
        self::assertSame('PNG', $values[1]);
        self::assertSame('plain', $values[2]);
    }

    #[Test]
    public function positional_values_decode_xml_parts_into_structures(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => 'Content-Type: application/xml', 'content' => '<note><from>A</from></note>'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/xml')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['from' => 'A'], $values[0]);
    }

    #[Test]
    public function positional_values_recurse_into_nested_multipart_by_prefix_positions(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'array'),
                new Schema(
                    type: 'array',
                    prefixItems: [
                        new Schema(type: 'object'),
                        new Schema(type: 'string'),
                        new Schema(),
                    ],
                ),
            ],
        );
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => 'Content-Type: application/json', 'content' => '[1,2]'],
                ['headers' => 'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY, 'content' => $this->innerBody()],
            ],
            prefixEncoding: [
                new Encoding(),
                new Encoding(
                    contentType: 'multipart/mixed',
                    prefixEncoding: [
                        new Encoding(contentType: 'application/xml'),
                        new Encoding(),
                        new Encoding(contentType: 'image/png'),
                    ],
                ),
            ],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame([1, 2], $values[0]);
        self::assertSame(['from' => 'A'], $values[1][0]);
        self::assertSame('hello', $values[1][1]);
        self::assertSame('png-bytes', $values[1][2]);
    }

    #[Test]
    public function nested_parts_without_schema_or_encoding_stay_raw(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Type: multipart/form-data; boundary=' . self::INNER_BOUNDARY,
                    'content' => $this->innerBody(),
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/mixed')],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );

        self::assertSame(['from' => 'A'], $values[0][0]);
        self::assertSame('hello', $values[0][1]);
        self::assertSame('png-bytes', $values[0][2]);
    }

    #[Test]
    public function nested_part_declared_only_in_the_encoding_infers_the_boundary_from_the_body(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Disposition: form-data; name="doc"',
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
                    ]),
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/mixed')],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );

        self::assertSame(['hello'], $values[0]);
    }

    #[Test]
    public function nested_part_without_a_boundary_in_header_or_body_yields_no_values(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Disposition: form-data; name="doc"',
                    'content' => 'not multipart framed content',
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/mixed')],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );

        self::assertSame([], $values[0]);
    }

    #[Test]
    public function nested_named_level_maps_parts_by_disposition_names(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(
                    type: 'object',
                    properties: [
                        'title' => new Schema(type: 'string'),
                        'count' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Type: multipart/form-data; boundary=' . self::INNER_BOUNDARY,
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        ['headers' => 'Content-Disposition: form-data; name="title"', 'content' => 'report'],
                        ['headers' => 'Content-Disposition: form-data; name="count"', 'content' => '2'],
                    ]),
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/form-data')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['title' => 'report', 'count' => '2'], $values[0]);
    }

    #[Test]
    public function nested_named_level_is_detected_through_the_encoding_map(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Disposition: form-data; name="doc"',
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        ['headers' => 'Content-Disposition: form-data; name="inner"', 'content' => 'payload'],
                    ]),
                ],
            ],
            prefixEncoding: [
                new Encoding(
                    contentType: 'multipart/form-data',
                    encoding: ['inner' => new Encoding(contentType: 'text/plain')],
                ),
            ],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );

        self::assertSame(['inner' => 'payload'], $values[0]);
    }

    #[Test]
    public function nested_positional_level_is_detected_through_prefix_encoding_without_schema(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Type: multipart/form-data; boundary=' . self::INNER_BOUNDARY,
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        ['headers' => 'Content-Type: text/plain', 'content' => 'first'],
                        ['headers' => 'Content-Type: text/plain', 'content' => 'second'],
                    ]),
                ],
            ],
            prefixEncoding: [
                new Encoding(contentType: 'multipart/mixed', prefixEncoding: [new Encoding(), new Encoding()]),
            ],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );

        self::assertSame(['first', 'second'], $values[0]);
    }

    #[Test]
    public function positional_values_fall_back_to_items_schema_without_prefix_items(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', items: new Schema(type: 'string'));
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => 'Content-Type: text/plain', 'content' => 'first'],
                ['headers' => 'Content-Type: text/plain', 'content' => 'second'],
            ],
            prefixEncoding: null,
            itemEncoding: new Encoding(contentType: 'text/plain'),
            schema: $schema,
        );

        self::assertSame(['first', 'second'], $values);
    }

    #[Test]
    public function explicit_part_schema_overrides_the_positional_schema_for_value_shaping(): void
    {
        $resolver = $this->resolver();
        $value = $resolver->positionalValue(
            part: ['headers' => '', 'content' => '{"author":"a"}'],
            index: 0,
            schema: new Schema(type: 'array', items: new Schema(type: 'string')),
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            partSchema: new Schema(type: 'object'),
        );

        self::assertSame(['author' => 'a'], $value);
    }

    #[Test]
    public function wildcard_encoding_decodes_json_when_the_position_expects_a_structured_value(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['author' => 'a'], $values[0]);
    }

    #[Test]
    public function explicit_part_header_prevents_wildcard_json_decoding(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => 'Content-Type: image/png', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame('{"author":"a"}', $values[0]);
    }

    #[Test]
    public function wildcard_encoding_decodes_json_when_the_position_declares_properties(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(properties: ['author' => new Schema(type: 'string')]),
            ],
        );
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: '*/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['author' => 'a'], $values[0]);
    }

    #[Test]
    public function wildcard_encoding_keeps_raw_content_for_unstructured_positions(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', prefixItems: [new Schema()]);
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame('{"author":"a"}', $values[0]);
    }

    #[Test]
    public function wildcard_encoding_keeps_raw_content_when_json_decoding_fails(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => 'not json {'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame('not json {', $values[0]);
    }

    #[Test]
    public function wildcard_encoding_keeps_raw_content_without_a_position_schema(): void
    {
        $resolver = $this->resolver();
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: '*/*')],
            itemEncoding: null,
            schema: null,
        );

        self::assertSame('{"author":"a"}', $values[0]);
    }

    #[Test]
    public function wildcard_encoding_decodes_json_when_the_position_carries_items(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'array', items: new Schema(type: 'object')),
            ],
        );
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['author' => 'a'], $values[0]);
    }

    #[Test]
    public function wildcard_encoding_decodes_json_for_union_object_types(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: ['string', 'object']),
            ],
        );
        $values = $resolver->positionalValues(
            parts: [
                ['headers' => '', 'content' => '{"author":"a"}'],
            ],
            prefixEncoding: [new Encoding(contentType: 'application/*')],
            itemEncoding: null,
            schema: $schema,
        );

        self::assertSame(['author' => 'a'], $values[0]);
    }

    #[Test]
    public function named_values_collect_union_array_properties_into_lists(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'file' => new Schema(type: ['array', 'string'], items: new Schema()),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="file"', 'content' => 'a'],
                ['headers' => 'Content-Disposition: form-data; name="file"', 'content' => 'b'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['file' => ['a', 'b']], $values);
    }

    #[Test]
    public function nesting_deeper_than_the_default_limit_throws_the_domain_exception(): void
    {
        $resolver = $this->resolver();

        $this->expectException(MultipartNestingDepthException::class);
        $this->expectExceptionMessage('Maximum multipart nesting depth of 1 exceeded');

        $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY,
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        [
                            'headers' => 'Content-Type: multipart/mixed; boundary=innermost',
                            'content' => $this->body('innermost', [
                                ['headers' => 'Content-Type: text/plain', 'content' => 'deep'],
                            ]),
                        ],
                    ]),
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/mixed')],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );
    }

    #[Test]
    public function custom_depth_limit_throws_earlier(): void
    {
        $resolver = new MultipartInstanceResolver(
            multipartParser: new MultipartBodyParser(),
            xmlParser: new XmlBodyParser(),
            maxNestingDepth: 0,
        );

        $this->expectException(MultipartNestingDepthException::class);

        $resolver->positionalValues(
            parts: [
                [
                    'headers' => 'Content-Type: multipart/mixed; boundary=' . self::INNER_BOUNDARY,
                    'content' => $this->innerBody(),
                ],
            ],
            prefixEncoding: [new Encoding(contentType: 'multipart/mixed')],
            itemEncoding: null,
            schema: new Schema(type: 'array'),
        );
    }

    #[Test]
    public function named_values_collect_multiple_files_into_one_array_property(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'file' => new Schema(type: 'array', items: new Schema()),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="file"; filename="a.txt"', 'content' => 'content a'],
                ['headers' => 'Content-Disposition: form-data; name="file"; filename="b.txt"', 'content' => 'content b'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['file' => ['content a', 'content b']], $values);
    }

    #[Test]
    public function named_values_wrap_a_single_file_into_a_single_item_array(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'file' => new Schema(type: 'array', items: new Schema()),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="file"', 'content' => 'content a'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['file' => ['content a']], $values);
    }

    #[Test]
    public function named_values_map_scalar_properties_by_part_name(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'title' => new Schema(type: 'string'),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="title"', 'content' => 'report'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['title' => 'report'], $values);
    }

    #[Test]
    public function named_values_keep_unknown_part_names_as_raw_values(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'object', properties: ['title' => new Schema(type: 'string')]);
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="extra"', 'content' => 'surprise'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['extra' => 'surprise'], $values);
    }

    #[Test]
    public function named_values_collect_duplicate_names_against_scalar_properties(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'object', properties: ['file' => new Schema(type: 'string')]);
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Disposition: form-data; name="file"', 'content' => 'a'],
                ['headers' => 'Content-Disposition: form-data; name="file"', 'content' => 'b'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['file' => ['a', 'b']], $values);
    }

    #[Test]
    public function named_values_skip_parts_without_a_disposition_name(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(type: 'object', properties: ['title' => new Schema(type: 'string')]);
        $values = $resolver->namedValues(
            parts: [
                ['headers' => 'Content-Type: text/plain', 'content' => 'anonymous'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame([], $values);
    }

    #[Test]
    public function named_values_recurse_into_a_nested_multipart_property(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'doc' => new Schema(
                    type: 'object',
                    properties: [
                        'title' => new Schema(type: 'string'),
                        'meta' => new Schema(type: 'object'),
                    ],
                ),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                [
                    'headers' => "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: multipart/mixed; boundary=" . self::INNER_BOUNDARY,
                    'content' => $this->body(self::INNER_BOUNDARY, [
                        ['headers' => 'Content-Disposition: form-data; name="title"', 'content' => 'report'],
                        ['headers' => "Content-Disposition: form-data; name=\"meta\"\r\nContent-Type: application/json", 'content' => '{"pages":3}'],
                    ]),
                ],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['title' => 'report', 'meta' => ['pages' => 3]], $values['doc']);
    }

    #[Test]
    public function named_values_decode_part_content_by_the_part_content_type(): void
    {
        $resolver = $this->resolver();
        $schema = new Schema(
            type: 'object',
            properties: [
                'meta' => new Schema(type: 'object'),
                'doc' => new Schema(type: 'string'),
            ],
        );
        $values = $resolver->namedValues(
            parts: [
                ['headers' => "Content-Disposition: form-data; name=\"meta\"\r\nContent-Type: application/json", 'content' => '{"size":2}'],
                ['headers' => "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: application/xml", 'content' => '<note><to>B</to></note>'],
            ],
            encoding: null,
            schema: $schema,
        );

        self::assertSame(['size' => 2], $values['meta']);
        self::assertSame(['to' => 'B'], $values['doc']);
    }

    private function resolver(): MultipartInstanceResolver
    {
        return new MultipartInstanceResolver(
            multipartParser: new MultipartBodyParser(),
            xmlParser: new XmlBodyParser(),
        );
    }

    private function innerBody(): string
    {
        return $this->body(self::INNER_BOUNDARY, [
            ['headers' => 'Content-Type: application/xml', 'content' => '<note><from>A</from></note>'],
            ['headers' => 'Content-Type: text/plain', 'content' => 'hello'],
            ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
        ]);
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function body(string $boundary, array $parts): string
    {
        $body = '';

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n"
                . $part['headers'] . "\r\n"
                . "\r\n"
                . $part['content'] . "\r\n";
        }

        return $body . "--{$boundary}--";
    }
}
