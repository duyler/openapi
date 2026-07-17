<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SchemaValidatorLazyInitTest extends TestCase
{
    #[Test]
    public function repeated_validate_calls_reuse_cached_registry(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'string');

        $validator->validate('hello', $schema);
        $validator->validate('world', $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_null_data_and_nullable_schema(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'string', nullable: true);

        $validator->validate(null, $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function registry_eager_initialized_in_constructor(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool, BuiltinFormats::create());

        $registry = self::readEffectiveRegistry($validator);

        $this->assertInstanceOf(
            ValidatorRegistryInterface::class,
            $registry,
            'effectiveRegistry MUST be initialized eagerly in __construct, not lazily on first validate(). '
            . 'Lazy init via ??= is racy under Swoole/FrankenPHP-threaded coroutine scheduling.',
        );
    }

    #[Test]
    public function registry_eager_initialized_in_constructor_without_explicit_registry(): void
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool, BuiltinFormats::create());

        $registry = self::readEffectiveRegistry($validator);

        $this->assertNotNull($registry);
    }

    #[Test]
    public function provided_registry_is_used_as_effective_registry(): void
    {
        $pool = new ValidatorPool();
        $explicitRegistry = $this->createStub(ValidatorRegistryInterface::class);

        $validator = new SchemaValidator(
            pool: $pool,
            formatRegistry: BuiltinFormats::create(),
            registry: $explicitRegistry,
        );

        $registry = self::readEffectiveRegistry($validator);

        $this->assertSame($explicitRegistry, $registry);
    }

    private static function readEffectiveRegistry(SchemaValidator $validator): ValidatorRegistryInterface
    {
        $prop = new ReflectionProperty(SchemaValidator::class, 'effectiveRegistry');

        return $prop->getValue($validator);
    }
}
