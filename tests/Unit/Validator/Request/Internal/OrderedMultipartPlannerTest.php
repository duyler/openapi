<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidEncodingConfigurationException;
use Duyler\OpenApi\Validator\Exception\PartContentTypeMismatchError;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\Internal\MultipartEncodingApplier;
use Duyler\OpenApi\Validator\Request\Internal\MultipartInstanceResolver;
use Duyler\OpenApi\Validator\Request\Internal\OrderedMultipartPlan;
use Duyler\OpenApi\Validator\Request\Internal\OrderedMultipartPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderedMultipartPlanner::class)]
#[CoversClass(OrderedMultipartPlan::class)]
final class OrderedMultipartPlannerTest extends TestCase
{
    #[Test]
    public function plan_maps_parts_to_prefix_items_and_item_encoding_by_position(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"author":"a"}'],
            ['headers' => 'Content-Type: text/plain', 'content' => 'trailing'],
            ['headers' => 'Content-Type: text/plain', 'content' => 'extra'],
        ];
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(contentType: 'application/json'), new Encoding()],
            itemEncoding: new Encoding(contentType: 'text/plain'),
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'object')],
            items: new Schema(type: 'string'),
        );

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame(['author' => 'a'], $plan->instance[0]);
        self::assertSame('trailing', $plan->instance[1]);
        self::assertSame('extra', $plan->instance[2]);
        self::assertCount(2, $plan->prefixEncoding ?? []);
        self::assertNotNull($plan->itemEncoding);
        self::assertSame($parts, $plan->parts);
    }

    #[Test]
    public function shorter_instance_ignores_extra_prefix_encodings(): void
    {
        $planner = $this->planner();
        $applier = new MultipartEncodingApplier(new MultipartBodyParser());
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"author":"a"}'],
        ];
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(), new Encoding(contentType: 'image/*')],
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'object'), new Schema()],
        );

        $plan = $planner->plan($parts, $mediaType, $schema);

        $planner->apply($plan, $applier);

        self::assertSame([['author' => 'a']], $plan->instance);
    }

    #[Test]
    public function binary_part_stays_raw_content(): void
    {
        $planner = $this->planner();
        $binary = "PNG\x00\x01\xFF\xFE";
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"author":"a"}'],
            ['headers' => 'Content-Type: image/png', 'content' => $binary],
        ];
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(contentType: 'application/json'), new Encoding(contentType: 'image/*')],
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'object'), new Schema()],
        );

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame($binary, $plan->instance[1]);
    }

    #[Test]
    public function json_part_without_content_type_header_is_decoded_by_schema_default(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Disposition: form-data; name="meta"', 'content' => '{"author":"a"}'],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding()]);
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame([['author' => 'a']], $plan->instance);
    }

    #[Test]
    public function text_part_with_json_like_content_stays_a_raw_string(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Type: text/plain', 'content' => '{"author":"a"}'],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding()]);
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'string')]);

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame(['{"author":"a"}'], $plan->instance);
    }

    #[Test]
    public function instance_values_drop_the_trailing_line_break(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Type: text/plain', 'content' => " Annual Report \r\n"],
            ['headers' => 'Content-Type: text/plain', 'content' => "second\n"],
        ];
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(contentType: 'text/plain'), new Encoding(contentType: 'text/plain')],
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'string'), new Schema(type: 'string')],
        );

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame(' Annual Report ', $plan->instance[0]);
        self::assertSame('second', $plan->instance[1]);
    }

    #[Test]
    public function apply_with_positional_only_configuration_is_a_no_op(): void
    {
        $planner = $this->planner();
        $applier = new MultipartEncodingApplier(new MultipartBodyParser());
        $parts = [
            [
                'headers' => "Content-Disposition: form-data; name=\"doc\"\r\nContent-Type: text/plain",
                'content' => 'not xml',
            ],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'text/plain')]);
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'string')]);

        $plan = $planner->plan($parts, $mediaType, $schema);

        $planner->apply($plan, $applier);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function encoding_field_together_with_prefix_encoding_is_rejected(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(
            encoding: ['doc' => new Encoding(contentType: 'application/xml')],
            prefixEncoding: [new Encoding()],
        );

        $this->expectException(InvalidEncodingConfigurationException::class);
        $this->expectExceptionMessage('MUST NOT be present');

        $planner->plan([], $mediaType, new Schema(type: 'array'));
    }

    #[Test]
    public function encoding_field_together_with_item_encoding_is_rejected(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(
            encoding: ['doc' => new Encoding(contentType: 'application/xml')],
            itemEncoding: new Encoding(),
        );

        $this->expectException(InvalidEncodingConfigurationException::class);

        $planner->plan([], $mediaType, new Schema(type: 'array'));
    }

    #[Test]
    public function positional_encoding_without_array_schema_or_item_schema_is_rejected(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(prefixEncoding: [new Encoding()]);

        $this->expectException(InvalidEncodingConfigurationException::class);
        $this->expectExceptionMessage('itemSchema or an array schema');

        $planner->plan([], $mediaType, new Schema(type: 'object'));
    }

    #[Test]
    public function item_schema_satisfies_the_array_model_requirement(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(
            itemSchema: new Schema(type: 'string'),
            prefixEncoding: [new Encoding(contentType: 'text/plain')],
        );

        $plan = $planner->plan(
            [['headers' => 'Content-Type: text/plain', 'content' => 'first']],
            $mediaType,
            new Schema(),
        );

        self::assertSame('first', $plan->instance[0]);
    }

    #[Test]
    public function prefix_items_keyword_satisfies_the_array_model_requirement(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(prefixEncoding: [new Encoding()]);

        $plan = $planner->plan(
            [['headers' => 'Content-Type: application/json', 'content' => '{"a":1}']],
            $mediaType,
            new Schema(prefixItems: [new Schema(type: 'object')]),
        );

        self::assertSame(['a' => 1], $plan->instance[0]);
    }

    #[Test]
    public function union_type_with_array_entry_satisfies_the_array_model_requirement(): void
    {
        $planner = $this->planner();
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'application/json')]);

        $plan = $planner->plan(
            [['headers' => 'Content-Type: application/json', 'content' => '{"a":1}']],
            $mediaType,
            new Schema(type: ['array', 'null']),
        );

        self::assertSame([['a' => 1]], $plan->instance);
    }

    #[Test]
    public function malformed_json_falls_back_to_the_raw_content(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"author":'],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'application/json')]);
        $schema = new Schema(type: 'array', prefixItems: [new Schema()]);

        $plan = $planner->plan($parts, $mediaType, $schema);

        self::assertSame('{"author":', $plan->instance[0]);
    }

    #[Test]
    public function boolean_items_schema_is_treated_as_type_absent(): void
    {
        $planner = $this->planner();
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"a":1}'],
            ['headers' => '', 'content' => '{"b":2}'],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'application/json')]);

        $plan = $planner->plan(
            $parts,
            $mediaType,
            new Schema(type: 'array', prefixItems: [new Schema(type: 'object')], items: false),
        );

        self::assertSame(['a' => 1], $plan->instance[0]);
        self::assertSame('{"b":2}', $plan->instance[1]);
    }

    #[Test]
    public function apply_applies_positional_encoding_content_types(): void
    {
        $planner = $this->planner();
        $applier = new MultipartEncodingApplier(new MultipartBodyParser());
        $parts = [
            ['headers' => 'Content-Type: application/json', 'content' => '{"author":"a"}'],
            ['headers' => 'Content-Type: image/png', 'content' => 'png-bytes'],
        ];
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(contentType: 'application/json'), new Encoding(contentType: 'image/png')],
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'object'), new Schema()],
        );

        $plan = $planner->plan($parts, $mediaType, $schema);

        $planner->apply($plan, $applier);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_reports_content_type_mismatch_with_prefix_keyword_and_index(): void
    {
        $planner = $this->planner();
        $applier = new MultipartEncodingApplier(new MultipartBodyParser());
        $parts = [
            ['headers' => 'Content-Type: text/plain', 'content' => 'not json'],
        ];
        $mediaType = new MediaType(prefixEncoding: [new Encoding(contentType: 'application/json')]);
        $schema = new Schema(type: 'array', prefixItems: [new Schema(type: 'object')]);

        $plan = $planner->plan($parts, $mediaType, $schema);

        try {
            $planner->apply($plan, $applier);
            $this->fail('Expected PartContentTypeMismatchError for the mismatching part content type');
        } catch (PartContentTypeMismatchError $exception) {
            self::assertSame('prefixEncoding', $exception->keyword());
            self::assertSame(0, $exception->params()['partIndex']);

            return;
        }
    }

    private function planner(): OrderedMultipartPlanner
    {
        return new OrderedMultipartPlanner(
            instanceResolver: new MultipartInstanceResolver(
                multipartParser: new MultipartBodyParser(),
                xmlParser: new XmlBodyParser(),
            ),
        );
    }
}
