<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormatValidatorIntegrationTest extends TestCase
{
    private ValidatorPool $pool;
    private FormatRegistry $registry;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->registry = BuiltinFormats::create();
    }

    #[Test]
    public function validate_string_with_datetime_format(): void
    {
        $schema = new Schema(
            type: 'string',
            format: 'date-time',
        );
        $validator = new FormatValidator($this->pool, $this->registry);

        $this->expectNotToPerformAssertions();
        $validator->validate('2024-01-15T10:30:00Z', $schema);
    }

    #[Test]
    public function validate_string_with_email_format(): void
    {
        $schema = new Schema(
            type: 'string',
            format: 'email',
        );
        $validator = new FormatValidator($this->pool, $this->registry);

        $this->expectNotToPerformAssertions();
        $validator->validate('test@example.com', $schema);
    }

    #[Test]
    public function validate_custom_format(): void
    {
        $customValidator = new class implements \Duyler\OpenApi\Validator\Format\FormatValidatorInterface {
            public function validate(mixed $data): void
            {
                if (false === is_string($data) || !str_starts_with($data, 'custom-')) {
                    throw new InvalidFormatException('custom', $data, 'Invalid custom format');
                }
            }
        };

        $registry = $this->registry->registerFormat('string', 'custom', $customValidator);
        $schema = new Schema(
            type: 'string',
            format: 'custom',
        );
        $validator = new FormatValidator($this->pool, $registry);

        $this->expectNotToPerformAssertions();
        $validator->validate('custom-value', $schema);
    }

    #[Test]
    public function skip_validation_for_unknown_format(): void
    {
        $schema = new Schema(
            type: 'string',
            format: 'unknown-format',
        );
        $validator = new FormatValidator($this->pool, $this->registry);

        $this->expectNotToPerformAssertions();
        $validator->validate('any-value', $schema);
    }

    #[Test]
    public function override_builtin_format(): void
    {
        $customValidator = new class implements \Duyler\OpenApi\Validator\Format\FormatValidatorInterface {
            public function validate(mixed $data): void
            {
                if ($data !== 'custom-email@example.com') {
                    throw new InvalidFormatException('email', $data, 'Must be custom email');
                }
            }
        };

        $registry = $this->registry->registerFormat('string', 'email', $customValidator);
        $schema = new Schema(
            type: 'string',
            format: 'email',
        );
        $validator = new FormatValidator($this->pool, $registry);

        $this->expectNotToPerformAssertions();
        $validator->validate('custom-email@example.com', $schema);
    }
}
