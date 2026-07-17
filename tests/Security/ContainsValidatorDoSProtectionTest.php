<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TooManyContainsValidationsError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function memory_get_usage;
use function sprintf;

/**
 * P-032 regression: ContainsValidator must terminate after 10000 matching
 * validations rather than walking a 100k-element attacker payload end to
 * end. Without the cap, a spec like `contains: {complex schema}` without
 * maxItems becomes a CPU-exhaustion vector.
 *
 * @internal
 */
final class ContainsValidatorDoSProtectionTest extends TestCase
{
    private ContainsValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $this->validator = new ContainsValidator(new ValidatorDependencies(pool: $pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function contains_validator_terminates_after_ten_thousand_validations(): void
    {
        $containsSchema = new Schema(type: 'object');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $data = array_fill(0, 100001, ['ok' => true]);

        $before = memory_get_usage();
        $caught = null;

        try {
            $this->validator->validate($data, $schema);
        } catch (TooManyContainsValidationsError $e) {
            $caught = $e;
        }

        $after = memory_get_usage();
        $delta = $after - $before;

        self::assertNotNull(
            $caught,
            'ContainsValidator must abort after the MAX_CONTAINS_VALIDATIONS cap (P-032)',
        );
        self::assertLessThan(
            50 * 1024 * 1024,
            $delta,
            sprintf('Contains validation memory must stay bounded; got %.2f MB', $delta / 1024 / 1024),
        );
    }
}
