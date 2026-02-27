<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Registry;

use Duyler\OpenApi\Validator\Exception\UnknownValidatorException;
use Duyler\OpenApi\Validator\Registry\DefaultValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DefaultValidatorRegistryTest extends TestCase
{
    private ValidatorPool $pool;
    private DefaultValidatorRegistry $registry;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->registry = new DefaultValidatorRegistry($this->pool);
    }

    #[Test]
    public function getValidator_returns_type_validator(): void
    {
        $validator = $this->registry->getValidator(TypeValidator::class);

        self::assertInstanceOf(TypeValidator::class, $validator);
    }

    #[Test]
    public function getValidator_returns_format_validator(): void
    {
        $validator = $this->registry->getValidator(FormatValidator::class);

        self::assertInstanceOf(FormatValidator::class, $validator);
    }

    #[Test]
    public function getValidator_throws_exception_for_unknown_type(): void
    {
        $this->expectException(UnknownValidatorException::class);

        $this->registry->getValidator('UnknownValidator');
    }

    #[Test]
    public function getValidator_throws_exception_with_type_in_message(): void
    {
        $this->expectExceptionMessage('Unknown validator type: UnknownValidator');

        $this->registry->getValidator('UnknownValidator');
    }

    #[Test]
    public function getAllValidators_returns_iterable(): void
    {
        $validators = $this->registry->getAllValidators();

        self::assertIsIterable($validators);
    }

    #[Test]
    public function getAllValidators_returns_all_validators(): void
    {
        $validators = $this->registry->getAllValidators();

        self::assertNotEmpty($validators);
        self::assertIsArray($validators);
        self::assertArrayHasKey(TypeValidator::class, $validators);
    }

    #[Test]
    public function getAllValidators_contains_type_validator(): void
    {
        $validators = $this->registry->getAllValidators();

        self::assertArrayHasKey(TypeValidator::class, $validators);
        self::assertInstanceOf(TypeValidator::class, $validators[TypeValidator::class]);
    }

    #[Test]
    public function getAllValidators_contains_format_validator(): void
    {
        $validators = $this->registry->getAllValidators();

        self::assertArrayHasKey(FormatValidator::class, $validators);
    }

    #[Test]
    public function getAllValidators_returns_correct_number_of_validators(): void
    {
        $validators = $this->registry->getAllValidators();

        self::assertCount(26, $validators);
    }

    #[Test]
    public function formatRegistry_property_is_accessible(): void
    {
        self::assertObjectHasProperty('formatRegistry', $this->registry);
    }

    #[Test]
    public function getValidator_returns_same_instance_on_multiple_calls(): void
    {
        $validator1 = $this->registry->getValidator(TypeValidator::class);
        $validator2 = $this->registry->getValidator(TypeValidator::class);

        self::assertSame($validator1, $validator2);
    }

    #[Test]
    public function getAllValidators_returns_same_instances_on_multiple_calls(): void
    {
        $validators1 = $this->registry->getAllValidators();
        $validators2 = $this->registry->getAllValidators();

        self::assertSame($validators1, $validators2);
    }
}
