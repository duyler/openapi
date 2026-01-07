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
    public function deserialize_array_with_form_style(): void
    {
        $param = new Parameter(name: 'tags', in: 'query', style: 'form', explode: false);
        $result = $this->deserializer->deserialize(['tag1', 'tag2'], $param);

        $this->assertSame('tag1,tag2', $result);
    }

    #[Test]
    public function deserialize_array_with_form_style_exploded(): void
    {
        $param = new Parameter(name: 'tags', in: 'query', style: 'form', explode: true);
        $result = $this->deserializer->deserialize(['tag1', 'tag2'], $param);

        $this->assertSame(['tag1', 'tag2'], $result);
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
    public function deserialize_int_value_as_string(): void
    {
        $param = new Parameter(name: 'count', in: 'query');
        $result = $this->deserializer->deserialize('42', $param);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function deserialize_bool_value_as_string(): void
    {
        $param = new Parameter(name: 'active', in: 'query');
        $result = $this->deserializer->deserialize('true', $param);

        $this->assertSame('true', $result);
    }

    #[Test]
    public function deserialize_with_matrix_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'matrix');
        $result = $this->deserializer->deserialize(';id=value', $param);

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
    public function deserialize_with_simple_style(): void
    {
        $param = new Parameter(name: 'id', in: 'path', style: 'simple');
        $result = $this->deserializer->deserialize('value', $param);

        $this->assertSame('value', $result);
    }
}
