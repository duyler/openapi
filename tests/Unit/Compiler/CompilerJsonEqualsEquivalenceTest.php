<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\Internal\EqualityHelpers;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function assert;
use function sprintf;
use function var_export;

use const INF;
use const NAN;
use const PHP_INT_MAX;

/**
 * A-023 contract: the compiler-emitted jsonEquals/arraysEqual pair and the
 * runtime JsonEquals must answer identically on every input, so neither
 * implementation can drift without breaking this test.
 */
#[CoversClass(EqualityHelpers::class)]
#[CoversClass(JsonEquals::class)]
final class CompilerJsonEqualsEquivalenceTest extends TestCase
{
    private object $compiled;

    protected function setUp(): void
    {
        $this->compiled = $this->compileProbe();
    }

    #[Test]
    #[DataProvider('provide_equivalence_inputs')]
    public function compiled_json_equals_matches_runtime_json_equals(mixed $a, mixed $b): void
    {
        self::assertSame(
            JsonEquals::equals($a, $b),
            $this->compiled->equals($a, $b),
            sprintf('Compiled jsonEquals must mirror JsonEquals for input (%s, %s).', var_export($a, true), var_export($b, true)),
        );
    }

    public static function provide_equivalence_inputs(): array
    {
        $boundary = 9007199254740992;

        return [
            'int_float_equal' => [1, 1.0],
            'float_int_equal' => [1.0, 1],
            'zero_int_float' => [0, 0.0],
            'distinct_numbers' => [1, 2],
            'int_vs_numeric_string' => [1, '1'],
            'number_vs_bool' => [1, true],
            'boundary_int_vs_boundary_float' => [$boundary, (float) $boundary],
            'above_boundary_int_vs_float' => [$boundary + 1, (float) $boundary],
            'int64_max_pair' => [PHP_INT_MAX, PHP_INT_MAX],
            'int64_max_vs_float' => [PHP_INT_MAX, (float) PHP_INT_MAX],
            'int64_close_distinct' => [PHP_INT_MAX, PHP_INT_MAX - 1],
            'negative_int64_min_vs_float' => [-9223372036854775807 - 1, (float) (-9223372036854775807 - 1)],
            'nan_vs_nan' => [NAN, NAN],
            'nan_vs_number' => [NAN, 1.0],
            'inf_vs_inf' => [INF, INF],
            'inf_vs_negative_inf' => [INF, -INF],
            'bool_true_vs_true' => [true, true],
            'bool_true_vs_false' => [true, false],
            'string_pair' => ['a', 'a'],
            'string_vs_int' => ['1', 1],
            'null_pair' => [null, null],
            'null_vs_false' => [null, false],
            'empty_arrays' => [[], []],
            'identical_lists' => [[1, 2, 3], [1, 2, 3]],
            'reordered_lists' => [[1, 2, 3], [3, 2, 1]],
            'list_length_mismatch' => [[1, 2], [1, 2, 3]],
            'int_float_in_lists' => [[1], [1.0]],
            'reordered_object_keys' => [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]],
            'object_extra_key' => [['a' => 1], ['a' => 1, 'b' => 2]],
            'object_value_mismatch' => [['a' => 1], ['a' => 2]],
            'nested_object_order' => [
                ['outer' => ['a' => 1, 'b' => 2], 'list' => [1, 2]],
                ['list' => [1, 2], 'outer' => ['b' => 2, 'a' => 1]],
            ],
            'nested_list_order_significant' => [
                ['list' => [1, 2]],
                ['list' => [2, 1]],
            ],
            'array_vs_scalar' => [[1], 1],
            'int_float_in_nested_objects' => [
                ['v' => 1],
                ['v' => 1.0],
            ],
        ];
    }

    private function compileProbe(): object
    {
        $emitted = new EqualityHelpers()->renderJsonEqualsInline();

        /**
         * The probe wraps the exact code emitted by EqualityHelpers into an
         * anonymous class; the compiled-validator eval contract is documented
         * in ValidatorCompilerRuntimeEquivalenceTest.
         */
        $probe = eval('return new class {' . $emitted . 'public function equals(mixed $a, mixed $b): bool { return $this->jsonEquals($a, $b); } };');

        assert(null !== $probe);

        return $probe;
    }
}
