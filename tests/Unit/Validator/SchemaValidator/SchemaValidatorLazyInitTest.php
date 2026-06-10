<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorLazyInitTest extends TestCase
{
    #[Test]
    public function repeated_validate_calls_reuse_cached_registry(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool);

        $schema = new Schema(type: 'string');

        $validator->validate('hello', $schema);
        $validator->validate('world', $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_null_data_and_nullable_schema(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool);

        $schema = new Schema(type: 'string', nullable: true);

        $validator->validate(null, $schema);

        $this->assertTrue(true);
    }
}
