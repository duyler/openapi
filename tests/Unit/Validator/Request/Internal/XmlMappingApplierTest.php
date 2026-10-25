<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Test\Support\RecordingEventDispatcher;
use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Duyler\OpenApi\Validator\Request\Internal\XmlMappingApplier;
use Duyler\OpenApi\Validator\Request\Internal\XmlValueReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function libxml_use_internal_errors;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

#[CoversClass(XmlMappingApplier::class)]
#[CoversClass(XmlValueReader::class)]
final class XmlMappingApplierTest extends TestCase
{
    private XmlMappingApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new XmlMappingApplier();
    }

    #[Test]
    public function root_text_node_type_returns_trimmed_text(): void
    {
        $element = $this->element('<value>  text  </value>');
        $schema = new Schema(type: 'string', xml: new Xml(nodeType: 'text'));

        $this->assertSame('text', $this->applier->mapElement($element, $schema));
    }

    #[Test]
    public function root_cdata_node_type_returns_cdata_content(): void
    {
        $element = $this->element('<value><![CDATA[raw <b>data</b>]]></value>');
        $schema = new Schema(type: 'string', xml: new Xml(nodeType: 'cdata'));

        $this->assertSame('raw <b>data</b>', $this->applier->mapElement($element, $schema));
    }

    #[Test]
    public function legacy_attribute_field_maps_attribute(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'id' => new Schema(type: 'string', xml: new Xml(attribute: true)),
        ]);

        $mapped = $this->applier->mapElement($this->element('<row id="7"/>'), $schema);

        $this->assertSame(['id' => '7'], $mapped);
    }

    #[Test]
    public function attribute_name_override_is_applied(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'count' => new Schema(type: 'string', xml: new Xml(nodeType: 'attribute', name: 'total')),
        ]);

        $mapped = $this->applier->mapElement($this->element('<row total="5"/>'), $schema);

        $this->assertSame(['count' => '5'], $mapped);
    }

    #[Test]
    public function namespaced_attribute_is_matched_by_namespace_uri(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'count' => new Schema(
                type: 'string',
                xml: new Xml(nodeType: 'attribute', namespace: 'urn:counts'),
            ),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<row xmlns:c="urn:counts" c:count="3"/>'),
            $schema,
        );

        $this->assertSame(['count' => '3'], $mapped);
    }

    #[Test]
    public function element_property_absent_is_omitted_even_when_nullable(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'related' => new Schema(type: ['object', 'null']),
        ]);

        $mapped = $this->applier->mapElement($this->element('<row/>'), $schema);

        $this->assertSame([], $mapped);
    }

    #[Test]
    public function empty_element_maps_to_empty_object_when_schema_allows_object(): void
    {
        $schema = new Schema(type: ['object', 'null']);

        $mapped = $this->applier->mapElement($this->element('<related></related>'), $schema);

        $this->assertSame([], $mapped);
    }

    #[Test]
    public function empty_object_allowing_property_maps_to_empty_list_value(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'related' => new Schema(type: ['object', 'null']),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><related></related></doc>'),
            $schema,
        );

        $this->assertSame(['related' => []], $mapped);
    }

    #[Test]
    public function empty_element_prefers_object_branch_of_object_string_union(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'related' => new Schema(type: ['object', 'string']),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><related></related></doc>'),
            $schema,
        );

        $this->assertSame(['related' => []], $mapped);
    }

    #[Test]
    public function empty_element_maps_to_empty_string_for_scalar_schema(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'note' => new Schema(type: 'string'),
        ]);

        $mapped = $this->applier->mapElement($this->element('<doc><note></note></doc>'), $schema);

        $this->assertSame(['note' => ''], $mapped);
    }

    #[Test]
    public function empty_element_maps_to_empty_list_for_root_array_schema(): void
    {
        $mapped = $this->applier->mapElement($this->element('<tags></tags>'), new Schema(type: 'array'));

        $this->assertSame([], $mapped);
    }

    #[Test]
    public function empty_element_maps_to_empty_object_for_untyped_schema(): void
    {
        $mapped = $this->applier->mapElement($this->element('<payload/>'), new Schema());

        $this->assertSame([], $mapped);
    }

    #[Test]
    public function repeated_element_children_collect_into_list(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'name' => new Schema(type: 'string'),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<row><name>a</name><name>b</name></row>'),
            $schema,
        );

        $this->assertSame(['name' => ['a', 'b']], $mapped);
    }

    #[Test]
    public function wrapped_array_with_multiple_wrappers_passes_through_with_warning(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $applier = new XmlMappingApplier($dispatcher);
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                xml: new Xml(nodeType: 'element'),
                items: new Schema(type: 'string'),
            ),
        ]);

        $mapped = $applier->mapElement(
            $this->element('<doc><animals><animal>d</animal></animals><animals><animal>c</animal></animals></doc>'),
            $schema,
        );

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame(
            ['animals' => [['animal' => 'd'], ['animal' => 'c']]],
            $mapped,
        );
    }

    #[Test]
    public function unwrapped_array_with_wrapper_shaped_document_passes_through_with_warning(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $applier = new XmlMappingApplier($dispatcher);
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $mapped = $applier->mapElement(
            $this->element('<doc><animals><animal>dog</animal></animals></doc>'),
            $schema,
        );

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame(['animals' => ['animal' => 'dog']], $mapped);
    }

    #[Test]
    public function unwrapped_array_items_collected_from_parent_node(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><animal>dog</animal><animal>cat</animal></doc>'),
            $schema,
        );

        $this->assertSame(['animals' => ['dog', 'cat']], $mapped);
    }

    #[Test]
    public function unwrapped_array_without_matches_yields_empty_list(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $mapped = $this->applier->mapElement($this->element('<doc/>'), $schema);

        $this->assertSame(['animals' => []], $mapped);
    }

    #[Test]
    public function boolean_items_schema_reads_items_generically(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(type: 'array', items: true),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><animals>dog</animals><animals>cat</animals></doc>'),
            $schema,
        );

        $this->assertSame(['animals' => ['dog', 'cat']], $mapped);
    }

    #[Test]
    public function wrapped_array_without_wrapper_or_items_yields_nothing_with_warning(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $applier = new XmlMappingApplier($dispatcher);
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                xml: new Xml(wrapped: true),
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $mapped = $applier->mapElement($this->element('<doc><title>t</title></doc>'), $schema);

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame([], $mapped);
    }

    #[Test]
    public function ambiguous_wrapped_array_without_dispatcher_passes_through_silently(): void
    {
        $applier = new XmlMappingApplier();
        $schema = new Schema(type: 'object', properties: [
            'animals' => new Schema(
                type: 'array',
                xml: new Xml(wrapped: true),
                items: new Schema(type: 'string', xml: new Xml(name: 'animal')),
            ),
        ]);

        $mapped = $applier->mapElement(
            $this->element('<doc><animal>dog</animal></doc>'),
            $schema,
        );

        $this->assertSame(['animals' => 'dog'], $mapped);
    }

    #[Test]
    public function named_prefix_items_map_in_declared_order(): void
    {
        $schema = new Schema(
            type: 'array',
            xml: new Xml(nodeType: 'element', name: 'OneTwoThree'),
            prefixItems: [
                new Schema(type: 'string', xml: new Xml(name: 'One')),
                new Schema(type: ['boolean', 'null'], xml: new Xml(name: 'Three')),
            ],
        );

        $mapped = $this->applier->mapElement(
            $this->element('<OneTwoThree><Three xsi:nil="true" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/><One>text</One></OneTwoThree>'),
            $schema,
        );

        $this->assertSame(['text', null], $mapped);
    }

    #[Test]
    public function unnamed_prefix_item_falls_back_to_legacy_read_with_warning(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $applier = new XmlMappingApplier($dispatcher);
        $schema = new Schema(
            type: 'array',
            xml: new Xml(nodeType: 'element', name: 'list'),
            prefixItems: [
                new Schema(type: 'string'),
            ],
        );

        $mapped = $applier->mapElement($this->element('<list><item>a</item></list>'), $schema);

        $this->assertCount(1, $dispatcher->events);
        $this->assertSame(['item' => 'a'], $mapped);
        $this->assertSame('list', $dispatcher->events[0]->propertyName);
        $this->assertSame('list', $dispatcher->events[0]->propertyPath);
        $this->assertSame(
            'XML mapping for "list" is ambiguous; the node is passed through without XML Object mapping',
            $dispatcher->events[0]->message,
        );
    }

    #[Test]
    public function root_array_without_item_name_reads_children_as_list(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'string'));

        $mapped = $this->applier->mapElement(
            $this->element('<items><one>1</one><two>2</two></items>'),
            $schema,
        );

        $this->assertSame(['1', '2'], $mapped);
    }

    #[Test]
    public function root_array_without_item_name_collects_prefixed_children(): void
    {
        $schema = new Schema(type: 'array', items: new Schema(type: 'string'));

        $mapped = $this->applier->mapElement(
            $this->element('<items xmlns="urn:d" xmlns:a="urn:a"><one>1</one><a:two>2</a:two></items>'),
            $schema,
        );

        $this->assertSame(['1', '2'], $mapped);
    }

    #[Test]
    public function ref_and_dynamic_ref_schemas_default_to_node_type_none(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'related' => new Schema(ref: '#/components/schemas/Related'),
            'payload' => new Schema(dynamicRef: '#meta'),
            'title' => new Schema(type: 'string'),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><related id="1"/><payload>x</payload><title>t</title></doc>'),
            $schema,
        );

        $this->assertSame(['title' => 't'], $mapped);
    }

    #[Test]
    public function free_form_object_schema_falls_back_to_legacy_read(): void
    {
        $schema = new Schema(type: 'object');

        $mapped = $this->applier->mapElement(
            $this->element('<doc any="x"><title>t</title></doc>'),
            $schema,
        );

        $this->assertSame(['@any' => 'x', 'title' => 't'], $mapped);
    }

    #[Test]
    public function inline_node_type_without_properties_or_items_contributes_nothing(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'meta' => new Schema(type: 'string', xml: new Xml(nodeType: 'none')),
            'title' => new Schema(type: 'string'),
        ]);

        $mapped = $this->applier->mapElement($this->element('<doc><title>t</title></doc>'), $schema);

        $this->assertSame(['title' => 't'], $mapped);
    }

    #[Test]
    public function nil_child_of_array_item_is_null(): void
    {
        $schema = new Schema(type: 'object', properties: [
            'related' => new Schema(type: ['object', 'null']),
        ]);

        $mapped = $this->applier->mapElement(
            $this->element('<doc><related xsi:nil="true" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/></doc>'),
            $schema,
        );

        $this->assertSame(['related' => null], $mapped);
    }

    #[Test]
    public function text_value_trims_element_content(): void
    {
        $this->assertSame('inner', XmlValueReader::textValue($this->element('<a>  inner  </a>')));
    }

    #[Test]
    public function is_xsi_nil_rejects_other_namespaces_and_values(): void
    {
        $this->assertFalse(XmlValueReader::isXsiNil(
            $this->element('<a xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:nil="false"/>'),
        ));
        $this->assertFalse(XmlValueReader::isXsiNil($this->element('<a x:nil="true" xmlns:x="urn:other"/>')));
    }

    #[Test]
    public function reader_keeps_namespaced_attributes_with_prefix_keys(): void
    {
        $read = XmlValueReader::read(
            $this->element('<root xmlns:atom="http://www.w3.org/2005/Atom"><atom:link href="u"/></root>'),
        );

        $this->assertSame(['atom:link' => ['@href' => 'u']], $read);
    }

    #[Test]
    public function reader_keeps_namespaced_only_attributes(): void
    {
        $read = XmlValueReader::read(
            $this->element('<root xmlns:atom="http://www.w3.org/2005/Atom"><atom:link atom:href="u"/></root>'),
        );

        $this->assertSame(['atom:link' => ['@atom:href' => 'u']], $read);
    }

    #[Test]
    public function reader_counts_prefixed_children_of_prefixed_element(): void
    {
        $read = XmlValueReader::read(
            $this->element('<root xmlns:atom="http://www.w3.org/2005/Atom"><atom:feed><atom:t>a</atom:t></atom:feed></root>'),
        );

        $this->assertSame(['atom:feed' => ['atom:t' => 'a']], $read);
    }

    #[Test]
    public function reader_collects_repeated_namespaced_children(): void
    {
        $read = XmlValueReader::read(
            $this->element('<feed xmlns:atom="http://www.w3.org/2005/Atom"><atom:t>a</atom:t><atom:t>b</atom:t></feed>'),
        );

        $this->assertSame(['atom:t' => ['a', 'b']], $read);
    }

    private function element(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $element = LibxmlSecuredContext::run(
                static fn(): SimpleXMLElement|false => simplexml_load_string(
                    $xml,
                    options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
                ),
            );
        } finally {
            libxml_use_internal_errors($previous);
        }

        $this->assertNotFalse($element);

        return $element;
    }
}
