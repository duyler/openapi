<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;

final readonly class TestValidator implements FormatValidatorInterface
{
    public function validate(mixed $data): void {}
}

final class FormatRegistryTest extends TestCase
{
    #[Test]
    public function register_custom_format(): void
    {
        $registry = new FormatRegistry();
        $validator = new TestValidator();

        $newRegistry = $registry->registerFormat('string', 'custom', $validator);

        $this->assertNotSame($registry, $newRegistry);
        $this->assertNull($registry->getValidator('string', 'custom'));
        $this->assertSame($validator, $newRegistry->getValidator('string', 'custom'));
    }

    #[Test]
    public function get_validator_for_registered_format(): void
    {
        $registry = new FormatRegistry();
        $validator = new TestValidator();

        $registry = $registry->registerFormat('string', 'custom', $validator);

        $this->assertSame($validator, $registry->getValidator('string', 'custom'));
    }

    #[Test]
    public function return_null_for_unregistered_format(): void
    {
        $registry = new FormatRegistry();

        $this->assertNull($registry->getValidator('string', 'nonexistent'));
    }

    #[Test]
    public function return_new_instance_on_register(): void
    {
        $registry = new FormatRegistry();
        $validator = new TestValidator();

        $newRegistry = $registry->registerFormat('string', 'custom', $validator);

        $this->assertNotSame($registry, $newRegistry);
        $this->assertInstanceOf(FormatRegistry::class, $newRegistry);
    }

    #[Test]
    public function include_builtin_formats(): void
    {
        $registry = BuiltinFormats::create();

        $this->assertNotNull($registry->getValidator('string', 'email'));
        $this->assertNotNull($registry->getValidator('string', 'uri'));
        $this->assertNotNull($registry->getValidator('string', 'uuid'));
        $this->assertNotNull($registry->getValidator('string', 'date-time'));
        $this->assertNotNull($registry->getValidator('string', 'date'));
        $this->assertNotNull($registry->getValidator('string', 'time'));
        $this->assertNotNull($registry->getValidator('string', 'hostname'));
        $this->assertNotNull($registry->getValidator('string', 'ipv4'));
        $this->assertNotNull($registry->getValidator('string', 'ipv6'));
        $this->assertNotNull($registry->getValidator('string', 'byte'));
        $this->assertNotNull($registry->getValidator('number', 'float'));
        $this->assertNotNull($registry->getValidator('number', 'double'));
        $this->assertNotNull($registry->getValidator('string', 'duration'));
        $this->assertNotNull($registry->getValidator('string', 'json-pointer'));
        $this->assertNotNull($registry->getValidator('string', 'relative-json-pointer'));
    }

    #[Test]
    public function has_format_returns_true_for_registered_format(): void
    {
        $registry = new FormatRegistry();
        $validator = new TestValidator();

        $registry = $registry->registerFormat('string', 'custom', $validator);

        $this->assertTrue($registry->hasFormat('string', 'custom'));
        $this->assertFalse($registry->hasFormat('string', 'nonexistent'));
    }

    #[Test]
    public function all_returns_all_registered_validators(): void
    {
        $registry = new FormatRegistry();
        $validator1 = new TestValidator();
        $validator2 = new TestValidator();

        $registry = $registry->registerFormat('string', 'format1', $validator1);
        $registry = $registry->registerFormat('number', 'format2', $validator2);

        $all = $registry->all();

        $this->assertArrayHasKey('string', $all);
        $this->assertArrayHasKey('number', $all);
        $this->assertArrayHasKey('format1', $all['string']);
        $this->assertArrayHasKey('format2', $all['number']);
        $this->assertSame($validator1, $all['string']['format1']);
        $this->assertSame($validator2, $all['number']['format2']);
    }
}
