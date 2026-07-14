<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
use Duyler\OpenApi\Validator\SchemaValidator\EnumValidator;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorFactory;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidatorFactoryTest extends TestCase
{
    private ValidatorPool $pool;
    private FormatRegistry $formatRegistry;
    private ValidatorFactory $factory;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->formatRegistry = BuiltinFormats::create();
        $this->factory = new ValidatorFactory($this->pool, $this->formatRegistry);
    }

    #[Test]
    public function createAll_returns_29_validators(): void
    {
        $validators = $this->factory->createAll();

        self::assertCount(29, $validators);
    }

    #[Test]
    public function createAll_returns_validators_indexed_by_class_name(): void
    {
        $validators = $this->factory->createAll();

        self::assertArrayHasKey(TypeValidator::class, $validators);
        self::assertArrayHasKey(FormatValidator::class, $validators);
        self::assertArrayHasKey(AllOfValidator::class, $validators);
        self::assertArrayHasKey(ItemsValidator::class, $validators);
        self::assertArrayHasKey(PropertiesValidator::class, $validators);
        self::assertArrayHasKey(OneOfValidator::class, $validators);
        self::assertArrayHasKey(ContentEncodingValidator::class, $validators);
        self::assertArrayHasKey(ContentMediaTypeValidator::class, $validators);
    }

    #[Test]
    public function createAll_returns_correct_validator_instances(): void
    {
        $validators = $this->factory->createAll();

        self::assertInstanceOf(TypeValidator::class, $validators[TypeValidator::class]);
        self::assertInstanceOf(FormatValidator::class, $validators[FormatValidator::class]);
        self::assertInstanceOf(ContentEncodingValidator::class, $validators[ContentEncodingValidator::class]);
    }

    #[Test]
    public function createAll_contains_all_expected_validator_types(): void
    {
        $validators = $this->factory->createAll();
        $expectedClasses = [
            AllOfValidator::class,
            AdditionalPropertiesValidator::class,
            AnyOfValidator::class,
            ArrayLengthValidator::class,
            ConstValidator::class,
            ContainsValidator::class,
            ContentEncodingValidator::class,
            ContentMediaTypeValidator::class,
            DependentSchemasValidator::class,
            DeprecatedValidator::class,
            EnumValidator::class,
            FormatValidator::class,
            IfThenElseValidator::class,
            ItemsValidator::class,
            NotValidator::class,
            NumericRangeValidator::class,
            ObjectLengthValidator::class,
            OneOfValidator::class,
            PatternPropertiesValidator::class,
            PatternValidator::class,
            PrefixItemsValidator::class,
            PropertiesValidator::class,
            PropertyNamesValidator::class,
            ReadOnlyWriteOnlyValidator::class,
            RequiredValidator::class,
            StringLengthValidator::class,
            TypeValidator::class,
            UnevaluatedItemsValidator::class,
            UnevaluatedPropertiesValidator::class,
        ];

        foreach ($expectedClasses as $class) {
            self::assertArrayHasKey($class, $validators, "Missing validator: {$class}");
        }
    }

    #[Test]
    public function createStatelessList_returns_26_validators(): void
    {
        $validators = $this->factory->createStatelessList();

        self::assertCount(26, $validators);
    }

    #[Test]
    public function createStatelessList_excludes_context_handled_validators(): void
    {
        $validators = $this->factory->createStatelessList();

        foreach ($validators as $validator) {
            self::assertNotInstanceOf(ItemsValidator::class, $validator);
            self::assertNotInstanceOf(PropertiesValidator::class, $validator);
            self::assertNotInstanceOf(OneOfValidator::class, $validator);
        }
    }

    #[Test]
    public function createStatelessList_returns_list_not_associative_array(): void
    {
        $validators = $this->factory->createStatelessList();

        self::assertIsList($validators);
    }

    #[Test]
    public function createStatelessList_contains_type_and_format_validators(): void
    {
        $validators = $this->factory->createStatelessList();
        $found = array_any($validators, fn($validator) => $validator instanceof TypeValidator);

        self::assertTrue($found, 'TypeValidator should be in stateless list');
    }

    #[Test]
    public function createAll_returns_same_instances_on_repeated_calls(): void
    {
        $validators1 = $this->factory->createAll();
        $validators2 = $this->factory->createAll();

        self::assertNotSame($validators1, $validators2);
    }
}
