<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format;

use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BuiltinFormatsTest extends TestCase
{
    #[Test]
    public function create_returns_format_registry_with_builtin_formats(): void
    {
        $registry = BuiltinFormats::create();

        $this->assertNotNull($registry->getValidator('string', 'email'));
        $this->assertNotNull($registry->getValidator('string', 'uri'));
        $this->assertNotNull($registry->getValidator('string', 'uuid'));
        $this->assertNotNull($registry->getValidator('string', 'date-time'));
    }

    #[Test]
    public function create_returns_new_instance_each_time(): void
    {
        $first = BuiltinFormats::create();
        $second = BuiltinFormats::create();

        $this->assertNotSame($first, $second);
    }
}
