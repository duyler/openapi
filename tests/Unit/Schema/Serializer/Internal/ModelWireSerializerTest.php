<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelWireSerializer::class)]
final class ModelWireSerializerTest extends TestCase
{
    #[Test]
    public function to_array_emits_non_null_values_in_metadata_order(): void
    {
        $model = new class {
            public ?string $name = null;

            public ?string $url = null;

            public ?string $email = null;
        };
        $model->name = 'John Doe';
        $model->url = 'https://example.com';

        $result = ModelWireSerializer::toArray($model, [
            new FieldMetadata('name', 'name'),
            new FieldMetadata('url', 'url'),
            new FieldMetadata('email', 'email'),
        ]);

        self::assertSame([
            'name' => 'John Doe',
            'url' => 'https://example.com',
        ], $result);
    }

    #[Test]
    public function to_array_skips_null_fields(): void
    {
        $model = new class {
            public ?string $name = null;

            public ?string $url = null;
        };

        $result = ModelWireSerializer::toArray($model, [
            new FieldMetadata('name', 'name'),
            new FieldMetadata('url', 'url'),
        ]);

        self::assertSame([], $result);
    }

    #[Test]
    public function to_array_uses_open_api_name_as_wire_key(): void
    {
        $model = new class {
            public ?string $name = null;
        };
        $model->name = 'John Doe';

        $result = ModelWireSerializer::toArray($model, [
            new FieldMetadata('name', 'name2'),
        ]);

        self::assertSame(['name2' => 'John Doe'], $result);
    }

    #[Test]
    public function to_array_with_empty_fields_returns_empty_array(): void
    {
        $model = new class {
            public ?string $name = null;
        };

        $result = ModelWireSerializer::toArray($model, []);

        self::assertSame([], $result);
    }
}
