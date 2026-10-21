<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Tag;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function array_values;
use function sprintf;

#[CoversClass(ModelWireSerializer::class)]
final class ModelWireSerializerTest extends TestCase
{
    private const array WIRE_FIELD_MODELS = [
        Contact::class,
        License::class,
        ExternalDocs::class,
        Server::class,
        Tag::class,
        Discriminator::class,
        InfoObject::class,
        Parameter::class,
        PathItem::class,
        Operation::class,
        Response::class,
        RequestBody::class,
        Header::class,
        Components::class,
        SecurityScheme::class,
        MediaType::class,
        Encoding::class,
        Example::class,
        Xml::class,
        Link::class,
        OAuthFlow::class,
        OAuthFlows::class,
        OpenApiDocument::class,
    ];

    #[Test]
    public function wire_fields_open_api_names_are_unique_per_model(): void
    {
        foreach (self::WIRE_FIELD_MODELS as $modelClass) {
            $openApiNames = array_map(
                static fn(FieldMetadata $field): string => $field->openApiName,
                $modelClass::wireFields(),
            );

            self::assertSame(
                array_values(array_unique($openApiNames)),
                $openApiNames,
                sprintf('%s declares duplicate openApiName values', $modelClass),
            );
        }
    }

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
