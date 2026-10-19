<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Parser\Internal\ComponentTreeBuilder;
use Duyler\OpenApi\Schema\Parser\Internal\InlineExampleResolver;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\PathItemBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

#[CoversClass(InlineExampleResolver::class)]
final class InlineExampleResolverTest extends TestCase
{
    private InlineExampleResolver $resolver;

    private ComponentTreeBuilder $componentTreeBuilder;

    private PathItemBuilder $pathItemBuilder;

    protected function setUp(): void
    {
        $this->resolver = new InlineExampleResolver();

        $context = new OpenApiBuildContext();
        $this->componentTreeBuilder = new ComponentTreeBuilder($context);
        $this->pathItemBuilder = $context->pathItemBuilder;
    }

    #[Test]
    public function resolve_wraps_int_in_example(): void
    {
        $example = $this->resolver->resolve(42);

        self::assertInstanceOf(Example::class, $example);
        self::assertSame(42, $example->value);
    }

    #[Test]
    public function resolve_wraps_float_in_example(): void
    {
        $example = $this->resolver->resolve(1.5);

        self::assertInstanceOf(Example::class, $example);
        self::assertSame(1.5, $example->value);
    }

    #[Test]
    public function resolve_wraps_bool_in_example(): void
    {
        $example = $this->resolver->resolve(true);

        self::assertInstanceOf(Example::class, $example);
        self::assertTrue($example->value);
    }

    #[Test]
    public function resolve_wraps_string_in_example(): void
    {
        $example = $this->resolver->resolve('foo');

        self::assertInstanceOf(Example::class, $example);
        self::assertSame('foo', $example->value);
    }

    #[Test]
    public function resolve_wraps_null_in_example_with_null_value(): void
    {
        $example = $this->resolver->resolve(null);

        self::assertInstanceOf(Example::class, $example);
        self::assertNull($example->value);
    }

    #[Test]
    public function resolve_returns_null_for_array_map(): void
    {
        self::assertNull($this->resolver->resolve(['value' => 'foo']));
    }

    #[Test]
    public function resolve_returns_null_for_array_list(): void
    {
        self::assertNull($this->resolver->resolve([1, 2]));
    }

    #[Test]
    public function resolve_throws_on_object_garbage(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Inline example must be a scalar or null, got object.');

        $this->resolver->resolve(new stdClass());
    }

    #[Test]
    public function inline_example_same_outcome_for_media_type_and_parameter(): void
    {
        $vectors = [
            'int' => ['example' => 42],
            'float' => ['example' => 1.5],
            'bool' => ['example' => true],
            'string' => ['example' => 'foo'],
            'array map' => ['example' => ['value' => 'foo']],
            'array list' => ['example' => [1, 2]],
            'explicit null' => ['example' => null],
            'absent' => [],
            'object' => ['example' => new stdClass()],
        ];

        foreach ($vectors as $name => $data) {
            self::assertSame(
                $this->mediaTypeOutcome($data),
                $this->parameterOutcome($data),
                sprintf('Vector "%s" outcome mismatch', $name),
            );
        }
    }

    #[Test]
    #[DataProvider('scalar_example_provider')]
    public function media_type_and_parameter_wrap_scalar_example_identically(
        string $name,
        mixed $value,
    ): void {
        $mediaType = $this->componentTreeBuilder->buildMediaType(['example' => $value]);
        $parameter = $this->pathItemBuilder->buildParameter([
            'name' => 'limit',
            'in' => 'query',
            'example' => $value,
        ]);

        self::assertInstanceOf(Example::class, $mediaType->example, $name);
        self::assertInstanceOf(Example::class, $parameter->example, $name);
        self::assertSame($mediaType->example->value, $parameter->example->value, $name);
        self::assertSame($value, $parameter->example->value, $name);
    }

    public static function scalar_example_provider(): array
    {
        return [
            'int' => ['int', 42],
            'float' => ['float', 1.5],
            'bool' => ['bool', true],
            'string' => ['string', 'foo'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int|float|bool|string|array<array-key, mixed>|null|stdClass|class-string<InvalidSchemaException>
     */
    private function mediaTypeOutcome(array $data): mixed
    {
        try {
            return $this->componentTreeBuilder->buildMediaType($data)->example?->value;
        } catch (InvalidSchemaException) {
            return InvalidSchemaException::class;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int|float|bool|string|array<array-key, mixed>|null|stdClass|class-string<InvalidSchemaException>
     */
    private function parameterOutcome(array $data): mixed
    {
        try {
            return $this->pathItemBuilder
                ->buildParameter(['name' => 'limit', 'in' => 'query'] + $data)
                ->example?->value;
        } catch (InvalidSchemaException) {
            return InvalidSchemaException::class;
        }
    }
}
