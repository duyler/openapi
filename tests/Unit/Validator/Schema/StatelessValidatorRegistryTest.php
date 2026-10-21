<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
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
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_find;

final class StatelessValidatorRegistryTest extends TestCase
{
    #[Test]
    public function get_validators_returns_canonical_stateless_list_in_order(): void
    {
        $registry = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $classes = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $registry->getValidators(),
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
            NotValidator::class,
            NumericRangeValidator::class,
            ObjectLengthValidator::class,
            PatternPropertiesValidator::class,
            PatternValidator::class,
            PrefixItemsValidator::class,
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
    public function strict_formats_reach_format_validator(): void
    {
        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            strictFormats: true,
        );

        $formatValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof FormatValidator,
        );

        self::assertNotNull($formatValidator);
        self::assertInstanceOf(FormatValidator::class, $formatValidator);

        $this->expectException(InvalidFormatException::class);

        $formatValidator->validate('any-value', new Schema(type: 'string', format: 'unknown-format'));
    }

    #[Test]
    public function lenient_formats_reach_format_validator(): void
    {
        $registry = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $formatValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof FormatValidator,
        );

        self::assertNotNull($formatValidator);
        self::assertInstanceOf(FormatValidator::class, $formatValidator);

        $formatValidator->validate('any-value', new Schema(type: 'string', format: 'unknown-format'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function report_deprecated_reach_deprecated_validator(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            reportDeprecated: true,
            eventDispatcher: $dispatcher,
        );

        $deprecatedValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof DeprecatedValidator,
        );

        self::assertNotNull($deprecatedValidator);
        self::assertInstanceOf(DeprecatedValidator::class, $deprecatedValidator);

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
                'newField' => new Schema(type: 'string'),
            ],
        );

        $deprecatedValidator->validate(['oldField' => 'value', 'newField' => 'value'], $schema);

        self::assertNotNull($warningReceived);
        self::assertSame('oldField', $warningReceived->propertyName);
    }

    #[Test]
    public function silent_deprecated_reach_deprecated_validator(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            eventDispatcher: $dispatcher,
        );

        $deprecatedValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof DeprecatedValidator,
        );

        self::assertNotNull($deprecatedValidator);
        self::assertInstanceOf(DeprecatedValidator::class, $deprecatedValidator);

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
                'newField' => new Schema(type: 'string'),
            ],
        );

        $deprecatedValidator->validate(['oldField' => 'value', 'newField' => 'value'], $schema);

        self::assertNull($warningReceived);
    }

    #[Test]
    public function document_with_resolver_enables_ref_recursion(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Registry ref recursion', '1.0.0'),
            components: new Components(schemas: [
                'Strict' => new Schema(
                    type: 'object',
                    required: ['value'],
                    properties: ['value' => new Schema(type: 'integer', minimum: 0)],
                    additionalProperties: false,
                ),
            ]),
        );

        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            document: $document,
            refResolver: new RefResolver(),
        );

        $additionalPropertiesValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof AdditionalPropertiesValidator,
        );

        self::assertNotNull($additionalPropertiesValidator);
        self::assertInstanceOf(AdditionalPropertiesValidator::class, $additionalPropertiesValidator);

        $schema = new Schema(
            type: 'object',
            additionalProperties: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(TypeMismatchError::class);

        $additionalPropertiesValidator->validate(['extra' => 5], $schema);
    }

    #[Test]
    public function missing_document_context_keeps_ref_recursion_inert(): void
    {
        $registry = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $additionalPropertiesValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof AdditionalPropertiesValidator,
        );

        self::assertNotNull($additionalPropertiesValidator);
        self::assertInstanceOf(AdditionalPropertiesValidator::class, $additionalPropertiesValidator);

        $schema = new Schema(
            type: 'object',
            additionalProperties: new Schema(ref: '#/components/schemas/Strict'),
        );

        $additionalPropertiesValidator->validate(['extra' => 5], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function document_without_resolver_keeps_ref_recursion_inert(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Registry ref recursion without resolver', '1.0.0'),
            components: new Components(schemas: [
                'Strict' => new Schema(
                    type: 'object',
                    required: ['value'],
                    properties: ['value' => new Schema(type: 'integer', minimum: 0)],
                    additionalProperties: false,
                ),
            ]),
        );

        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            document: $document,
        );

        $additionalPropertiesValidator = array_find(
            $registry->getValidators(),
            static fn(SchemaValidatorInterface $validator): bool => $validator instanceof AdditionalPropertiesValidator,
        );

        self::assertNotNull($additionalPropertiesValidator);
        self::assertInstanceOf(AdditionalPropertiesValidator::class, $additionalPropertiesValidator);

        $schema = new Schema(
            type: 'object',
            additionalProperties: new Schema(ref: '#/components/schemas/Strict'),
        );

        $additionalPropertiesValidator->validate(['extra' => 5], $schema);

        $this->addToAssertionCount(1);
    }
}
