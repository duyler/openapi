<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Dto;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
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
use Duyler\OpenApi\Validator\SchemaValidator\RefResolvingSchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_any;
use function array_diff;
use function array_find;
use function array_map;
use function array_values;
use function in_array;
use function str_repeat;

final class SchemaValidatorDependenciesListTest extends TestCase
{
    private SchemaValidatorDependencies $dependencies;

    #[Override]
    protected function setUp(): void
    {
        $this->dependencies = new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        );
    }

    #[Test]
    public function create_full_list_returns_29_validators(): void
    {
        $validators = $this->dependencies->createFullList();

        self::assertCount(29, $validators);
    }

    #[Test]
    public function create_full_list_returns_validators_in_canonical_order(): void
    {
        $validators = $this->dependencies->createFullList();

        $classes = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $validators,
        );

        self::assertSame([
            AllOfValidator::class,
            AnyOfValidator::class,
            ArrayLengthValidator::class,
            ConstValidator::class,
            ContainsValidator::class,
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
            AdditionalPropertiesValidator::class,
            ContentEncodingValidator::class,
            ContentMediaTypeValidator::class,
        ], $classes);
    }

    #[Test]
    public function create_full_list_returns_correct_validator_instances(): void
    {
        $validators = $this->dependencies->createFullList();

        self::assertContainsOnlyInstancesOf(SchemaValidatorInterface::class, $validators);
        self::assertContainsTypeValidator($validators);
        self::assertContainsFormatValidator($validators);
        self::assertContainsContentValidators($validators);
    }

    #[Test]
    public function create_stateless_list_returns_26_validators(): void
    {
        $validators = $this->dependencies->createStatelessList();

        self::assertCount(26, $validators);
    }

    #[Test]
    public function create_stateless_list_excludes_context_handled_validators(): void
    {
        $validators = $this->dependencies->createStatelessList();

        foreach ($validators as $validator) {
            self::assertNotInstanceOf(ItemsValidator::class, $validator);
            self::assertNotInstanceOf(PropertiesValidator::class, $validator);
            self::assertNotInstanceOf(OneOfValidator::class, $validator);
        }
    }

    #[Test]
    public function create_stateless_list_returns_list_not_associative_array(): void
    {
        $validators = $this->dependencies->createStatelessList();

        self::assertIsList($validators);
    }

    #[Test]
    public function create_stateless_list_contains_type_and_format_validators(): void
    {
        $validators = $this->dependencies->createStatelessList();
        $found = array_any($validators, fn($validator) => $validator instanceof TypeValidator);

        self::assertTrue($found, 'TypeValidator should be in stateless list');
    }

    #[Test]
    public function repeated_calls_share_the_same_validator_instances(): void
    {
        $fullList1 = $this->dependencies->createFullList();
        $fullList2 = $this->dependencies->createFullList();
        $statelessList = $this->dependencies->createStatelessList();

        self::assertSame($fullList1[0], $fullList2[0]);
        self::assertSame($fullList1[23], $fullList2[23]);
        self::assertSame($fullList1[23], $statelessList[20]);
    }

    #[Test]
    public function create_full_list_propagates_format_registry_to_format_validator(): void
    {
        $dependencies = new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            strictFormats: true,
        );

        $formatValidator = array_find(
            $dependencies->createFullList(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof FormatValidator,
        );

        $schema = new Schema(type: 'string', format: 'unknown-format');

        $this->expectException(InvalidFormatException::class);
        $formatValidator->validate('any-value', $schema);
    }

    #[Test]
    public function create_stateless_list_is_full_list_minus_context_handled_in_order(): void
    {
        $fullList = $this->dependencies->createFullList();
        $statelessList = $this->dependencies->createStatelessList();

        $expected = array_values(array_filter(
            $fullList,
            static fn(SchemaValidatorInterface $validator): bool => false === in_array(
                $validator::class,
                [ItemsValidator::class, PropertiesValidator::class, OneOfValidator::class],
                true,
            ),
        ));

        $fullClasses = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $fullList,
        );
        $statelessClasses = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $statelessList,
        );
        $expectedClasses = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $expected,
        );

        self::assertCount(26, $statelessList);
        self::assertCount(3, array_diff($fullClasses, $statelessClasses));
        self::assertSame($expectedClasses, $statelessClasses);
    }

    #[Test]
    public function registry_path_runs_on_default_preg_executor_backtrack_limit(): void
    {
        $registry = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $patternValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof PatternValidator,
        );

        self::assertInstanceOf(PatternValidator::class, $patternValidator);

        $schema = new Schema(type: 'string', pattern: '^(a+)+$');
        $payload = str_repeat('a', 16) . 'b';

        $this->expectException(InvalidPatternException::class);
        $patternValidator->validate($payload, $schema);
    }

    #[Test]
    public function root_stateless_validator_is_memoized_per_dependencies(): void
    {
        $first = $this->dependencies->rootStatelessValidator();
        $second = $this->dependencies->rootStatelessValidator();

        self::assertSame($first, $second);
        self::assertInstanceOf(StatelessValidatorDispatcher::class, $first);
    }

    #[Test]
    public function root_stateless_validator_wraps_ref_resolution_when_document_and_resolver_present(): void
    {
        $document = $this->createDocument();
        $dependencies = new SchemaValidatorDependencies(
            pool: new ValidatorPool(),
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            document: $document,
        );

        $root = $dependencies->rootStatelessValidator();

        self::assertInstanceOf(RefResolvingSchemaValidator::class, $root);
        self::assertSame($root, $dependencies->rootStatelessValidator());
    }

    private static function assertContainsTypeValidator(array $validators): void
    {
        $found = array_find(
            $validators,
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof TypeValidator,
        );

        self::assertInstanceOf(TypeValidator::class, $found);
    }

    private static function assertContainsFormatValidator(array $validators): void
    {
        $found = array_find(
            $validators,
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof FormatValidator,
        );

        self::assertInstanceOf(FormatValidator::class, $found);
    }

    private static function assertContainsContentValidators(array $validators): void
    {
        $encoding = array_find(
            $validators,
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof ContentEncodingValidator,
        );
        $mediaType = array_find(
            $validators,
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof ContentMediaTypeValidator,
        );

        self::assertInstanceOf(ContentEncodingValidator::class, $encoding);
        self::assertInstanceOf(ContentMediaTypeValidator::class, $mediaType);
    }

    private function createDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('Shared list', '1.0.0'),
            components: new Components(schemas: []),
        );
    }
}
