<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\EnumScalarCache;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function var_export;

use const NAN;

#[CoversClass(ArrayLengthValidator::class)]
#[CoversClass(EnumScalarCache::class)]
final class ScalarKeyEquivalencePairTest extends TestCase
{
    private ArrayLengthValidator $uniqueItemsValidator;

    private EnumScalarCache $enumScalarCache;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $dependencies = new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            pool: $pool,
            formatRegistry: BuiltinFormats::create(),
        );
        $this->uniqueItemsValidator = new ArrayLengthValidator($dependencies);
        $this->enumScalarCache = new EnumScalarCache();
    }

    #[Test]
    #[DataProvider('provide_equivalence_vectors')]
    public function unique_items_and_enum_agree_on_scalar_equivalence(mixed $a, mixed $b): void
    {
        $uniqueItemsVerdict = $this->uniqueItemsVerdict($a, $b);
        $enumVerdict = $this->enumVerdict($a, $b);

        self::assertSame(
            $uniqueItemsVerdict,
            $enumVerdict,
            sprintf('uniqueItems and enum must agree on whether (%s, %s) are equivalent.', var_export($a, true), var_export($b, true)),
        );
    }

    #[Test]
    #[DataProvider('provide_equivalence_vectors')]
    public function scalar_equivalence_matches_json_equals_canon(mixed $a, mixed $b): void
    {
        $uniqueItemsVerdict = $this->uniqueItemsVerdict($a, $b);
        $canonVerdict = JsonEquals::equals($a, $b) ? 'EQUAL' : 'DISTINCT';

        self::assertSame(
            $canonVerdict,
            $uniqueItemsVerdict,
            sprintf('uniqueItems verdict for (%s, %s) must follow JsonEquals semantics.', var_export($a, true), var_export($b, true)),
        );
    }

    public static function provide_equivalence_vectors(): array
    {
        $boundary = 9007199254740992;

        return [
            'int_and_float_one' => [1, 1.0],
            'int_and_numeric_string' => [1, '1'],
            'bool_and_int' => [true, 1],
            'nan_and_nan' => [NAN, NAN],
            'boundary_int_and_boundary_float' => [$boundary, (float) $boundary],
            'zero_and_negative_zero' => [0, -0.0],
        ];
    }

    private function uniqueItemsVerdict(mixed $a, mixed $b): string
    {
        try {
            $this->uniqueItemsValidator->validate([$a, $b], new Schema(type: 'array', uniqueItems: true));
        } catch (DuplicateItemsError) {
            return 'EQUAL';
        }

        return 'DISTINCT';
    }

    private function enumVerdict(mixed $a, mixed $b): string
    {
        $schema = new Schema(enum: [$a]);

        if (false === $this->enumScalarCache->isScalarLookupEligible($schema, $b)) {
            return 'DISTINCT';
        }

        return $this->enumScalarCache->contains($schema, $b) ? 'EQUAL' : 'DISTINCT';
    }
}
