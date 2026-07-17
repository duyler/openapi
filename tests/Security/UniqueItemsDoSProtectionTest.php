<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TooManyItemsForUniqueCheckError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function memory_get_usage;
use function range;
use function sprintf;

/**
 * P-033 regression: ArrayLengthValidator must abort the unique-items
 * check after MAX_UNIQUE_CHECK (100000) unique entries with a fail-safe
 * TooManyItemsForUniqueCheckError rather than accumulating a hash-table
 * of unbounded size on attacker-controlled arrays without maxItems.
 *
 * @internal
 */
final class UniqueItemsDoSProtectionTest extends TestCase
{
    private ArrayLengthValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $this->validator = new ArrayLengthValidator(new ValidatorDependencies(pool: $pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function uniqueitems_check_fails_safe_on_one_hundred_thousand_unique_items(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $data = range(1, 100001);

        $before = memory_get_usage();
        $caught = null;

        try {
            $this->validator->validate($data, $schema);
        } catch (TooManyItemsForUniqueCheckError $e) {
            $caught = $e;
        }

        $after = memory_get_usage();
        $delta = $after - $before;

        self::assertNotNull(
            $caught,
            'ArrayLengthValidator must abort the unique-items check after MAX_UNIQUE_CHECK entries (P-033)',
        );
        self::assertLessThan(
            50 * 1024 * 1024,
            $delta,
            sprintf('Unique-items check memory must stay bounded; got %.2f MB', $delta / 1024 / 1024),
        );
    }
}
