<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

final class StatelessValidatorDispatcherTest extends TestCase
{
    private const string REF_TARGET = '#/components/schemas/Name';

    #[Test]
    public function validate_throws_first_validator_error_directly(): void
    {
        $dispatcher = $this->createDispatcher();

        try {
            $dispatcher->validate('invalid', new Schema(type: 'integer', minimum: 1, maximum: 100));
            self::fail('Expected TypeMismatchError for non-integer data');
        } catch (ValidationException) {
            self::fail('Dispatcher must propagate the first stateless validator error instead of aggregating it');
        } catch (TypeMismatchError $error) {
            self::assertSame('type', $error->keyword());
            self::assertStringContainsString('Expected type', $error->getMessage());
        }
    }

    #[Test]
    public function validate_honors_caller_context_nullable_as_type(): void
    {
        $dispatcher = $this->createDispatcher();
        $schema = new Schema(type: 'string', nullable: true);

        $lenient = ValidationContext::fromInit(new ValidationContextInit(
            pool: new ValidatorPool(),
            nullableAsType: true,
        ));
        $dispatcher->validate(null, $schema, $lenient);
        $this->addToAssertionCount(1);

        $this->expectException(TypeMismatchError::class);

        $strict = ValidationContext::fromInit(new ValidationContextInit(
            pool: new ValidatorPool(),
            nullableAsType: false,
        ));
        $dispatcher->validate(null, $schema, $strict);
    }

    #[Test]
    public function validate_creates_default_context_when_none_provided(): void
    {
        $dispatcher = $this->createDispatcher();

        try {
            $dispatcher->validate('abc', new Schema(type: 'integer'));
            self::fail('Expected TypeMismatchError for non-integer data');
        } catch (TypeMismatchError $error) {
            self::assertSame('/', $error->dataPath());
        }
    }

    #[Test]
    public function strict_formats_option_reaches_validators_through_list(): void
    {
        $strict = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            strictFormats: true,
        );
        $schema = new Schema(type: 'string', format: 'unknown-format');

        $this->expectException(InvalidFormatException::class);

        $this->createDispatcher($strict)->validate('any-value', $schema);
    }

    #[Test]
    public function lenient_formats_option_reaches_validators_through_list(): void
    {
        $lenient = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $this->createDispatcher($lenient)->validate('any-value', new Schema(type: 'string', format: 'unknown-format'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function report_deprecated_option_reaches_validators_through_list(): void
    {
        $warningReceived = null;
        $eventDispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $silent = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());
        $reporting = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            reportDeprecated: true,
            eventDispatcher: $eventDispatcher,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );
        $data = ['oldField' => 'value'];

        $this->createDispatcher($reporting)->validate($data, $schema);
        self::assertInstanceOf(ValidationWarningEvent::class, $warningReceived);
        self::assertSame('oldField', $warningReceived->propertyName);

        $warningReceived = null;
        $this->createDispatcher($silent)->validate($data, $schema);
        self::assertNull($warningReceived);
    }

    #[Test]
    public function top_level_items_are_dispatched_to_structural_validators(): void
    {
        $dispatcher = $this->createFullDispatcher();

        try {
            $dispatcher->validate(['not-an-integer'], new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
            ));
            self::fail('Expected nested item validation error');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();
            self::assertNotEmpty($errors);
            $error = $errors[0];
            self::assertInstanceOf(TypeMismatchError::class, $error);
            self::assertSame('type', $error->keyword());
        }
    }

    #[Test]
    public function top_level_one_of_is_dispatched_to_structural_validators(): void
    {
        $dispatcher = $this->createFullDispatcher();

        $this->expectException(ValidationException::class);

        $dispatcher->validate(42, new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'boolean'),
            ],
        ));
    }

    #[Test]
    public function full_list_from_dependencies_includes_structural_validators(): void
    {
        $dependencies = $this->createDependencies();

        $classes = array_map(
            static fn(SchemaValidatorInterface $validator): string => $validator::class,
            $dependencies->createFullList(),
        );

        self::assertCount(29, $classes);
        self::assertContains(ItemsValidator::class, $classes);
        self::assertContains(PropertiesValidator::class, $classes);
        self::assertContains(OneOfValidator::class, $classes);
    }

    #[Test]
    public function nested_composition_refs_resolve_through_registry_document(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Dispatcher nested refs', '1.0.0'),
            components: new Components(schemas: [
                'Name' => new Schema(type: 'string'),
            ]),
        );

        $registry = new StatelessValidatorRegistry(
            new ValidatorPool(),
            BuiltinFormats::create(),
            document: $document,
            refResolver: new RefResolver(),
        );

        try {
            $this->createDispatcher($registry)->validate(42, new Schema(
                allOf: [new Schema(ref: self::REF_TARGET)],
            ));
            self::fail('Expected nested validation error through resolved ref');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();
            self::assertNotEmpty($errors);
            $error = $errors[0];
            self::assertInstanceOf(AbstractValidationError::class, $error);
            self::assertSame('type', $error->keyword());
        }
    }

    #[Test]
    public function missing_document_context_keeps_nested_refs_inert(): void
    {
        $registry = new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        $this->createDispatcher($registry)->validate(42, new Schema(
            allOf: [new Schema(ref: self::REF_TARGET)],
        ));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function normal_depth_does_not_throw(): void
    {
        $dispatcher = $this->createFullDispatcher();

        $dispatcher->validate(['name' => 'test'], new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        ));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function deeply_nested_properties_throw_depth_exceeded(): void
    {
        $dispatcher = $this->createFullDispatcher();

        $current = new Schema(type: 'string');
        for ($i = 0; $i < 70; ++$i) {
            $current = new Schema(
                type: 'object',
                properties: ['nested' => $current],
            );
        }

        $data = [];
        for ($i = 0; $i < 70; ++$i) {
            $data = ['nested' => $data];
        }

        $this->expectException(SchemaDepthExceededException::class);
        $this->expectExceptionMessage('Maximum schema depth of 64 exceeded');

        $dispatcher->validate($data, $current);
    }

    #[Test]
    public function deeply_nested_items_throw_depth_exceeded(): void
    {
        $dispatcher = $this->createFullDispatcher();

        $current = new Schema(type: 'string');
        for ($i = 0; $i < 70; ++$i) {
            $current = new Schema(
                type: 'array',
                items: $current,
            );
        }

        $data = [];
        for ($i = 0; $i < 70; ++$i) {
            $data = [$data];
        }

        $this->expectException(SchemaDepthExceededException::class);

        $dispatcher->validate($data, $current);
    }

    private function createDispatcher(?StatelessValidatorRegistry $registry = null): StatelessValidatorDispatcher
    {
        $registry ??= new StatelessValidatorRegistry(new ValidatorPool(), BuiltinFormats::create());

        return new StatelessValidatorDispatcher(
            validators: $registry->getValidators(),
            pool: new ValidatorPool(),
        );
    }

    private function createFullDispatcher(): StatelessValidatorDispatcher
    {
        return new StatelessValidatorDispatcher(
            validators: $this->createDependencies()->createFullList(),
            pool: new ValidatorPool(),
        );
    }

    private function createDependencies(): SchemaValidatorDependencies
    {
        $pool = new ValidatorPool();

        return new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        );
    }
}
