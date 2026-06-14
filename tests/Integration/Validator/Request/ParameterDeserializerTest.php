<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ParameterDeserializerTest extends TestCase
{
    private ParameterDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ParameterDeserializer();
    }

    #[Test]
    public function deserialize_string_value(): void
    {
        $param = new Parameter(name: 'test', in: 'query');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_integer_as_string(): void
    {
        $param = new Parameter(name: 'count', in: 'query');
        $result = $this->deserializer->deserialize('42', $param);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function deserialize_number_as_string(): void
    {
        $param = new Parameter(name: 'price', in: 'query');
        $result = $this->deserializer->deserialize('19.99', $param);

        $this->assertSame('19.99', $result);
    }

    #[Test]
    public function deserialize_boolean_true_parameter(): void
    {
        $param = new Parameter(name: 'active', in: 'query');
        $result = $this->deserializer->deserialize('true', $param);

        $this->assertSame('true', $result);
    }

    #[Test]
    public function deserialize_boolean_false_parameter(): void
    {
        $param = new Parameter(name: 'active', in: 'query');
        $result = $this->deserializer->deserialize('false', $param);

        $this->assertSame('false', $result);
    }

    #[Test]
    public function deserialize_boolean_string_true_parameter(): void
    {
        $param = new Parameter(name: 'active', in: 'query');
        $result = $this->deserializer->deserialize('true', $param);

        $this->assertSame('true', $result);
    }

    #[Test]
    public function deserialize_boolean_string_false_parameter(): void
    {
        $param = new Parameter(name: 'active', in: 'query');
        $result = $this->deserializer->deserialize('false', $param);

        $this->assertSame('false', $result);
    }

    #[Test]
    public function deserialize_array_parameter(): void
    {
        $param = new Parameter(name: 'tags', in: 'query', style: 'form', explode: false);
        $result = $this->deserializer->deserialize(['tag1', 'tag2'], $param);

        $this->assertSame('tag1,tag2', $result);
    }

    #[Test]
    public function deserialize_array_with_explode(): void
    {
        $param = new Parameter(name: 'tags', in: 'query', style: 'form', explode: true);
        $result = $this->deserializer->deserialize(['tag1', 'tag2'], $param);

        $this->assertSame(['tag1', 'tag2'], $result);
    }

    #[Test]
    public function deserialize_array_with_integers(): void
    {
        $param = new Parameter(name: 'ids', in: 'query', style: 'form', explode: false);
        $result = $this->deserializer->deserialize([1, 2, 3], $param);

        $this->assertSame('1,2,3', $result);
    }

    #[Test]
    public function deserialize_array_with_integers_exploded(): void
    {
        $param = new Parameter(name: 'ids', in: 'query', style: 'form', explode: true);
        $result = $this->deserializer->deserialize([1, 2, 3], $param);

        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function deserialize_object_parameter(): void
    {
        $param = new Parameter(name: 'data', in: 'query', style: 'simple');
        $result = $this->deserializer->deserialize(['key1' => 'value1', 'key2' => 'value2'], $param);

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    #[Test]
    public function deserialize_object_as_form_implodes_values(): void
    {
        $param = new Parameter(name: 'data', in: 'query');
        $result = $this->deserializer->deserialize(['key1' => 'value1', 'key2' => 'value2'], $param);

        $this->assertSame('value1,value2', $result);
    }

    #[Test]
    public function deserialize_null_throws_exception(): void
    {
        $param = new Parameter(name: 'test', in: 'query');

        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Data must be array, int, string, float or bool, NULL given');

        $this->deserializer->deserialize(null, $param);
    }

    #[Test]
    public function deserialize_with_matrix_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'matrix');
        $result = $this->deserializer->deserialize(';id=value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_matrix_style_without_prefix(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'matrix');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_label_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'label');
        $result = $this->deserializer->deserialize('.value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_label_style_without_prefix(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'label');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_simple_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'simple');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_form_style_default(): void
    {
        $param = new Parameter(name: 'test', in: 'query');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_path_default_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_header_default_style(): void
    {
        $param = new Parameter(name: 'accept', in: 'header');
        $result = $this->deserializer->deserialize('application/json', $param);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function deserialize_with_cookie_default_style(): void
    {
        $param = new Parameter(name: 'session', in: 'cookie');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_with_cookie_style(): void
    {
        $param = new Parameter(name: 'session', in: 'cookie', style: 'cookie');
        $result = $this->deserializer->deserialize('abc123', $param);

        $this->assertSame('abc123', $result);
    }

    #[Test]
    public function deserialize_pipe_delimited(): void
    {
        $param = new Parameter(name: 'ids', in: 'query', style: 'pipeDelimited');
        $result = $this->deserializer->deserialize('1|2|3', $param);

        $this->assertSame(['1', '2', '3'], $result);
    }

    #[Test]
    public function deserialize_space_delimited(): void
    {
        $param = new Parameter(name: 'tags', in: 'query', style: 'spaceDelimited');
        $result = $this->deserializer->deserialize('foo bar baz', $param);

        $this->assertSame(['foo', 'bar', 'baz'], $result);
    }

    #[Test]
    public function deserialize_form_string_with_comma_no_explode(): void
    {
        $param = new Parameter(name: 'ids', in: 'query', style: 'form', explode: false);
        $result = $this->deserializer->deserialize('1,2,3', $param);

        $this->assertSame(['1', '2', '3'], $result);
    }

    #[Test]
    public function deserialize_form_string_without_comma_no_explode(): void
    {
        $param = new Parameter(name: 'id', in: 'query', style: 'form', explode: false);
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_unknown_style_returns_value(): void
    {
        $param = new Parameter(name: 'test', in: 'query', style: 'deepObject');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_simple_array_with_comma_separated_values_returns_array(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            style: 'simple',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('a,b,c', $param);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function deserialize_simple_string_with_comma_stays_string(): void
    {
        $param = new Parameter(
            name: 'X-Accept',
            in: 'header',
            style: 'simple',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize('text/html,application/json', $param);

        $this->assertSame('text/html,application/json', $result);
    }

    #[Test]
    public function deserialize_simple_array_single_value_returns_single_element_array(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            style: 'simple',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('solo', $param);

        $this->assertSame(['solo'], $result);
    }

    #[Test]
    public function deserialize_simple_array_empty_value_returns_empty_array(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            style: 'simple',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('', $param);

        $this->assertSame([], $result);
    }

    #[Test]
    public function deserialize_header_default_style_array_returns_array(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('blue,black,brown', $param);

        $this->assertSame(['blue', 'black', 'brown'], $result);
    }

    #[Test]
    public function deserialize_header_default_style_string_stays_string(): void
    {
        $param = new Parameter(
            name: 'X-Accept',
            in: 'header',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize('application/json', $param);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function deserialize_path_default_style_array_returns_array(): void
    {
        $param = new Parameter(
            name: 'ids',
            in: 'path',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('1,2,3', $param);

        $this->assertSame(['1', '2', '3'], $result);
    }

    #[Test]
    public function deserialize_path_default_style_string_stays_string(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize('abc-123', $param);

        $this->assertSame('abc-123', $result);
    }

    #[Test]
    public function deserialize_matrix_array_explode_false_returns_comma_separated_array(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'matrix',
            explode: false,
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize(';id=blue,black,brown', $param);

        $this->assertSame(['blue', 'black', 'brown'], $result);
    }

    #[Test]
    public function deserialize_matrix_array_explode_true_returns_repeated_name_array(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'matrix',
            explode: true,
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize(';id=blue;id=black;id=brown', $param);

        $this->assertSame(['blue', 'black', 'brown'], $result);
    }

    #[Test]
    public function deserialize_matrix_string_stays_string(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'matrix',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize(';id=value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_label_array_explode_false_returns_comma_separated_array(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'label',
            explode: false,
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('.blue,black,brown', $param);

        $this->assertSame(['blue', 'black', 'brown'], $result);
    }

    #[Test]
    public function deserialize_label_array_explode_true_returns_dot_separated_array(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'label',
            explode: true,
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('.blue.black.brown', $param);

        $this->assertSame(['blue', 'black', 'brown'], $result);
    }

    #[Test]
    public function deserialize_label_string_stays_string(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'path',
            style: 'label',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize('.value', $param);

        $this->assertSame('value', $result);
    }

    #[Test]
    public function deserialize_cookie_style_array_returns_array(): void
    {
        $param = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'cookie',
            schema: new Schema(type: 'array'),
        );

        $result = $this->deserializer->deserialize('a,b,c', $param);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function deserialize_cookie_style_string_stays_string(): void
    {
        $param = new Parameter(
            name: 'session',
            in: 'cookie',
            style: 'cookie',
            schema: new Schema(type: 'string'),
        );

        $result = $this->deserializer->deserialize('abc123', $param);

        $this->assertSame('abc123', $result);
    }

    #[Test]
    public function deserialize_simple_array_without_schema_stays_string(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            style: 'simple',
        );

        $result = $this->deserializer->deserialize('a,b,c', $param);

        $this->assertSame('a,b,c', $result);
    }

    #[Test]
    public function deserialize_simple_array_with_nullable_type_union_returns_array(): void
    {
        $param = new Parameter(
            name: 'X-Tags',
            in: 'header',
            style: 'simple',
            schema: new Schema(type: ['array', 'null']),
        );

        $result = $this->deserializer->deserialize('a,b,c', $param);

        $this->assertSame(['a', 'b', 'c'], $result);
    }
}
