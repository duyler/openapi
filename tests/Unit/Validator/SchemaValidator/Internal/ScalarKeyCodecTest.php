<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ScalarKeyCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use InvalidArgumentException;
use stdClass;

use function ini_get;
use function ini_set;
use function sprintf;
use function var_export;

use const INF;
use const NAN;
use const PHP_INT_MAX;

#[CoversClass(ScalarKeyCodec::class)]
final class ScalarKeyCodecTest extends TestCase
{
    private ScalarKeyCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new ScalarKeyCodec();
    }

    #[Test]
    public function null_encodes_to_null_key(): void
    {
        self::assertSame('null', $this->codec->encode(null));
    }

    #[Test]
    public function booleans_encode_to_distinct_bool_keys(): void
    {
        self::assertSame('b:1', $this->codec->encode(true));
        self::assertSame('b:0', $this->codec->encode(false));
    }

    #[Test]
    public function strings_encode_with_string_prefix(): void
    {
        self::assertSame('s:abc', $this->codec->encode('abc'));
        self::assertSame('s:', $this->codec->encode(''));
        self::assertSame('s:null', $this->codec->encode('null'));
    }

    #[Test]
    public function small_int_and_equal_float_share_one_key(): void
    {
        self::assertSame($this->codec->encode(1), $this->codec->encode(1.0));
        self::assertSame($this->codec->encode(0), $this->codec->encode(0.0));
        self::assertSame($this->codec->encode(-42), $this->codec->encode(-42.0));
    }

    #[Test]
    public function negative_zero_and_positive_zero_share_one_key(): void
    {
        self::assertSame($this->codec->encode(0.0), $this->codec->encode(-0.0));
        self::assertSame($this->codec->encode(0), $this->codec->encode(-0.0));
    }

    #[Test]
    public function int_boundary_value_and_float_boundary_value_share_one_key(): void
    {
        $boundary = 9007199254740992;

        self::assertSame($this->codec->encode($boundary), $this->codec->encode((float) $boundary));
    }

    #[Test]
    public function int_above_boundary_never_matches_float(): void
    {
        $boundary = 9007199254740992;

        self::assertNotSame($this->codec->encode($boundary + 1), $this->codec->encode((float) $boundary));
        self::assertNotSame($this->codec->encode($boundary + 1), $this->codec->encode(9007199254740993.0));
    }

    #[Test]
    public function distinct_ints_above_boundary_keep_precision(): void
    {
        self::assertNotSame($this->codec->encode(PHP_INT_MAX), $this->codec->encode(PHP_INT_MAX - 1));
        self::assertSame($this->codec->encode(PHP_INT_MAX), $this->codec->encode(PHP_INT_MAX));
    }

    #[Test]
    public function distinct_ints_near_boundary_do_not_collapse(): void
    {
        $boundary = 9007199254740992;

        self::assertNotSame($this->codec->encode($boundary - 1), $this->codec->encode($boundary - 2));
        self::assertNotSame($this->codec->encode((float) ($boundary - 1)), $this->codec->encode((float) ($boundary - 2)));
    }

    #[Test]
    public function distinct_floats_do_not_collapse_in_key(): void
    {
        self::assertNotSame($this->codec->encode(1.000000000000001), $this->codec->encode(1.000000000000002));
        self::assertNotSame($this->codec->encode(0.100000000000001), $this->codec->encode(0.100000000000002));
    }

    #[Test]
    public function nan_keys_are_unique_per_call(): void
    {
        $first = $this->codec->encode(NAN);
        $second = $this->codec->encode(NAN);

        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('/^n:nan:[0-9a-f]{16}$/', $first);
        self::assertMatchesRegularExpression('/^n:nan:[0-9a-f]{16}$/', $second);
    }

    #[Test]
    public function nan_key_never_collides_with_any_other_value(): void
    {
        $nanKey = $this->codec->encode(NAN);

        self::assertNotSame($nanKey, $this->codec->encode('NAN'));
        self::assertNotSame($nanKey, $this->codec->encode(1));
        self::assertNotSame($nanKey, $this->codec->encode(null));
    }

    #[Test]
    public function infinities_encode_distinctly(): void
    {
        self::assertSame($this->codec->encode(INF), $this->codec->encode(INF));
        self::assertNotSame($this->codec->encode(INF), $this->codec->encode(-INF));
    }

    #[Test]
    public function finite_float_keys_are_hex_encoded_doubles(): void
    {
        self::assertMatchesRegularExpression('/^n:[0-9a-f]{16}$/', $this->codec->encode(1.0));
        self::assertSame($this->codec->encode(1.5), $this->codec->encode(1.5));
    }

    #[Test]
    public function float_keys_do_not_depend_on_serialize_precision_ini(): void
    {
        $originalSerializePrecision = ini_get('serialize_precision');
        $originalPrecision = ini_get('precision');
        self::assertIsString($originalSerializePrecision);
        self::assertIsString($originalPrecision);

        try {
            ini_set('serialize_precision', '14');
            ini_set('precision', '14');

            self::assertNotSame($this->codec->encode(9007199254740990.0), $this->codec->encode(9007199254740991.0));
            self::assertNotSame($this->codec->encode(1.000000000000001), $this->codec->encode(1.000000000000002));
            self::assertSame($this->codec->encode(1), $this->codec->encode(1.0));
            self::assertSame($this->codec->encode(0), $this->codec->encode(-0.0));
        } finally {
            ini_set('serialize_precision', $originalSerializePrecision);
            ini_set('precision', $originalPrecision);
        }
    }

    #[Test]
    public function prefixes_do_not_collide_between_types(): void
    {
        self::assertNotSame($this->codec->encode(null), $this->codec->encode('null'));
        self::assertNotSame($this->codec->encode(0.0), $this->codec->encode('0.0'));
        self::assertNotSame($this->codec->encode(true), $this->codec->encode('b:1'));
        self::assertNotSame($this->codec->encode(0), $this->codec->encode(false));
    }

    #[Test]
    public function array_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec->encode([1]);
    }

    #[Test]
    public function object_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec->encode(new stdClass());
    }

    #[Test]
    public function key_equality_matches_json_equals_for_every_pair(): void
    {
        $boundary = 9007199254740992;
        $pairs = [
            [1, 1.0],
            [1.0, 1],
            [0, 0.0],
            [1, 2],
            [1, '1'],
            ['1', 1],
            [true, 1],
            [1, true],
            [false, 0],
            [true, true],
            [false, false],
            [true, false],
            [NAN, NAN],
            [NAN, 1.0],
            [$boundary, (float) $boundary],
            [$boundary + 1, (float) $boundary],
            [PHP_INT_MAX, PHP_INT_MAX],
            [PHP_INT_MAX, PHP_INT_MAX - 1],
            [$boundary - 1, $boundary - 2],
            [(float) ($boundary - 1), (float) ($boundary - 2)],
            [1.000000000000001, 1.000000000000002],
            [0.1, 0.1],
            [-0.0, 0.0],
            [0, -0.0],
            [INF, INF],
            [INF, -INF],
            ['a', 'a'],
            ['a', 'b'],
            [null, null],
            [null, false],
            [null, 0],
            [0.5, '0.5'],
            ['', ''],
        ];

        foreach ($pairs as $pair) {
            [$a, $b] = $pair;

            self::assertSame(
                JsonEquals::equals($a, $b),
                $this->codec->encode($a) === $this->codec->encode($b),
                sprintf('Key equality must match JsonEquals for pair (%s, %s).', var_export($a, true), var_export($b, true)),
            );
        }
    }

    #[Test]
    #[DataProvider('provide_scalar_values')]
    public function encode_returns_string_for_every_scalar_type(mixed $value): void
    {
        self::assertIsString($this->codec->encode($value));
    }

    public static function provide_scalar_values(): array
    {
        return [
            'null' => [null],
            'bool' => [true],
            'int' => [7],
            'float' => [7.5],
            'string' => ['seven'],
        ];
    }
}
