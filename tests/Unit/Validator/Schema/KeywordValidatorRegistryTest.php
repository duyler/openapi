<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\Internal\KeywordValidatorAdapter;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentRequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DynamicRefValidator;
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
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_search;
use function array_slice;
use function count;

final class KeywordValidatorRegistryTest extends TestCase
{
    private KeywordValidatorRegistry $registry;

    #[Override]
    protected function setUp(): void
    {
        $dependencies = new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        );

        $this->registry = KeywordValidatorRegistry::buildIn($dependencies);
    }

    #[Test]
    public function build_in_returns_builtin_validators_in_canonical_order(): void
    {
        $classes = $this->wrappedClasses($this->registry->all());

        self::assertSame([
            AllOfValidator::class,
            AnyOfValidator::class,
            ArrayLengthValidator::class,
            ConstValidator::class,
            ContainsValidator::class,
            DependentRequiredValidator::class,
            DependentSchemasValidator::class,
            DeprecatedValidator::class,
            DynamicRefValidator::class,
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
            AdditionalPropertiesValidator::class,
            ContentEncodingValidator::class,
            ContentMediaTypeValidator::class,
        ], $classes);
    }

    #[Test]
    public function build_in_registers_multi_keyword_builtins_under_each_keyword(): void
    {
        $minimum = $this->registry->get('minimum');

        self::assertInstanceOf(KeywordValidatorAdapter::class, $minimum);
        self::assertSame($minimum, $this->registry->get('maximum'));
        self::assertSame($minimum, $this->registry->get('exclusiveMinimum'));
        self::assertSame($minimum, $this->registry->get('exclusiveMaximum'));
        self::assertSame($minimum, $this->registry->get('multipleOf'));
        self::assertSame($this->registry->get('readOnly'), $this->registry->get('writeOnly'));
        self::assertSame($this->registry->get('minItems'), $this->registry->get('uniqueItems'));
    }

    #[Test]
    public function get_returns_null_for_unknown_keyword(): void
    {
        self::assertNull($this->registry->get('nonexistent'));
    }

    #[Test]
    public function with_replaces_builtin_keyword_slot_in_place(): void
    {
        $before = $this->registry->all();
        $numericRangeIndex = $this->firstIndexOfClass($before, NumericRangeValidator::class);

        $custom = new FakeKeywordValidator();
        $after = $this->registry->with('minimum', $custom)->all();

        self::assertCount(count($before) + 1, $after);
        self::assertSame($custom, $after[$numericRangeIndex]);
        self::assertInstanceOf(
            NumericRangeValidator::class,
            $after[$numericRangeIndex + 1] instanceof KeywordValidatorAdapter
                ? $after[$numericRangeIndex + 1]->validator()
                : $after[$numericRangeIndex + 1],
        );

        $classes = $this->wrappedClasses($after);
        unset($classes[$numericRangeIndex]);

        self::assertSame($this->wrappedClasses($before), array_values($classes));
    }

    #[Test]
    public function with_appends_new_keyword_after_the_whole_builtin_set(): void
    {
        $before = $this->registry->all();

        $custom = new FakeKeywordValidator();
        $after = $this->registry->with('x-custom', $custom)->all();

        self::assertCount(count($before) + 1, $after);
        self::assertSame($custom, $after[count($after) - 1]);
        self::assertSame($this->wrappedClasses($before), array_slice($this->wrappedClasses($after), 0, count($before)));
    }

    #[Test]
    public function with_keeps_original_registry_unchanged(): void
    {
        $originalMinimum = $this->registry->get('minimum');

        $custom = new FakeKeywordValidator();
        $updated = $this->registry->with('minimum', $custom);

        self::assertNotSame($this->registry, $updated);
        self::assertSame($originalMinimum, $this->registry->get('minimum'));
        self::assertSame($custom, $updated->get('minimum'));
    }

    #[Test]
    public function with_base_user_entries_override_base_keyword_slots(): void
    {
        $custom = new FakeKeywordValidator();
        $user = new KeywordValidatorRegistry()->with('minimum', $custom);

        $merged = $user->withBase($this->registry);

        self::assertSame($custom, $merged->get('minimum'));
        self::assertSame($this->registry->get('type'), $merged->get('type'));
    }

    #[Test]
    public function with_base_appends_user_only_keywords_at_the_end(): void
    {
        $custom = new FakeKeywordValidator();
        $user = new KeywordValidatorRegistry()->with('x-even', $custom);

        $merged = $user->withBase($this->registry);

        $classes = $this->wrappedClasses($merged->all());

        self::assertCount(32, $classes);
        self::assertSame($custom, $merged->all()[31]);
        self::assertSame($this->wrappedClasses($this->registry->all()), array_slice($classes, 0, 31));
    }

    #[Test]
    public function all_returns_each_builtin_validator_exactly_once(): void
    {
        $validators = $this->registry->all();

        self::assertCount(31, $validators);

        $classes = $this->wrappedClasses($validators);

        self::assertCount(31, array_unique($classes));
    }

    #[Test]
    public function with_replacing_every_keyword_of_multi_keyword_builtin_removes_it_from_all(): void
    {
        $custom = new FakeKeywordValidator();
        $replaced = $this->registry
            ->with('minimum', $custom)
            ->with('maximum', $custom)
            ->with('exclusiveMinimum', $custom)
            ->with('exclusiveMaximum', $custom)
            ->with('multipleOf', $custom);

        $classes = $this->wrappedClasses($replaced->all());

        self::assertCount(31, $classes);
        self::assertNotContains(NumericRangeValidator::class, $classes);
    }

    private function firstIndexOfClass(array $validators, string $class): int
    {
        $classes = $this->wrappedClasses($validators);
        $index = array_search($class, $classes, true);

        self::assertIsInt($index);

        return $index;
    }

    /**
     * @param list<KeywordValidatorInterface> $validators
     *
     * @return list<class-string>
     */
    private function wrappedClasses(array $validators): array
    {
        return array_map(
            static fn(KeywordValidatorInterface $validator): string => $validator instanceof KeywordValidatorAdapter
                ? $validator->validator()::class
                : $validator::class,
            $validators,
        );
    }
}

final readonly class FakeKeywordValidator implements KeywordValidatorInterface
{
    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return true;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void {}
}
