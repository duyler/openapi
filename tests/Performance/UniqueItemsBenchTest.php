<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UniqueItemsBenchTest extends TestCase
{
    #[Test]
    public function ten_thousand_unique_items_validate_under_100ms(): void
    {
        $validator = new ArrayLengthValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(type: 'array', uniqueItems: true);
        $data = array_map(
            static fn(int $i): string => 'value-' . $i,
            range(1, 10_000),
        );

        $start = microtime(true);

        $validator->validate($data, $schema);

        $duration = (microtime(true) - $start) * 1000.0;

        self::assertLessThan(100.0, $duration, '10 000 unique items should validate in under 100ms');
    }

    #[Test]
    public function ten_thousand_items_with_single_duplicate_fail_under_100ms(): void
    {
        $validator = new ArrayLengthValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(type: 'array', uniqueItems: true);
        $data = array_map(
            static fn(int $i): string => 'value-' . $i,
            range(1, 9_999),
        );
        $data[] = 'value-1';

        $start = microtime(true);

        $caught = null;
        try {
            $validator->validate($data, $schema);
        } catch (DuplicateItemsError $e) {
            $caught = $e;
        }

        $duration = (microtime(true) - $start) * 1000.0;

        self::assertNotNull($caught, 'Expected DuplicateItemsError for single duplicate in 10 000 items');
        self::assertLessThan(100.0, $duration, '10 000 items with one duplicate should fail in under 100ms');
    }
}
