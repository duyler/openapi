<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
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
        $this->expectExceptionMessage('Data must be array, int, string, float or bool, null given');

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
}
