<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/** @internal */
final class ParameterDeserializerExplodeLimitTest extends TestCase
{
    private ParameterDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ParameterDeserializer();
    }

    #[Test]
    public function form_style_large_comma_count_rejected(): void
    {
        $value = 'a' . str_repeat(',a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('query', 'form', false),
        );
    }

    #[Test]
    public function form_style_normal_comma_count_accepted(): void
    {
        $result = $this->deserializer->deserialize(
            'a,b,c',
            $this->arrayParameter('query', 'form', false),
        );

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function pipe_delimited_large_count_rejected(): void
    {
        $value = 'a' . str_repeat('|a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('query', 'pipeDelimited', false),
        );
    }

    #[Test]
    public function space_delimited_large_count_rejected(): void
    {
        $value = 'a' . str_repeat(' a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('query', 'spaceDelimited', false),
        );
    }

    #[Test]
    public function simple_style_large_comma_count_rejected(): void
    {
        $value = 'a' . str_repeat(',a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('path', 'simple', false),
        );
    }

    #[Test]
    public function simple_style_normal_comma_count_accepted(): void
    {
        $result = $this->deserializer->deserialize(
            'a,b,c',
            $this->arrayParameter('path', 'simple', false),
        );

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function matrix_style_explode_true_large_count_rejected(): void
    {
        // Matrix explode=true uses the repeated-name separator ';tags=' which
        // flows through splitBySeparator. Pins DoS guard on a path not
        // previously exercised by the form/pipe/space/simple test set.
        $value = ';tags=a' . str_repeat(';tags=a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('path', 'matrix', true),
        );
    }

    #[Test]
    public function label_style_explode_true_large_count_rejected(): void
    {
        // Label explode=true uses '.' as the separator (also via
        // splitBySeparator). Pins DoS guard on the dot-separated path.
        $value = '.a' . str_repeat('.a', 1000);

        $this->expectException(InvalidParameterException::class);

        $this->deserializer->deserialize(
            $value,
            $this->arrayParameter('path', 'label', true),
        );
    }

    private function arrayParameter(string $in, string $style, bool $explode): Parameter
    {
        return new Parameter(
            name: 'tags',
            in: $in,
            style: $style,
            explode: $explode,
            schema: new Schema(type: 'array', items: new Schema(type: 'string')),
        );
    }
}
