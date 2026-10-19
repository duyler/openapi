<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Components implements JsonSerializable
{
    /**
     * @param array<string, Schema>|null $schemas
     * @param array<string, Response>|null $responses
     * @param array<string, Parameter>|null $parameters
     * @param array<string, Example>|null $examples
     * @param array<string, RequestBody>|null $requestBodies
     * @param array<string, Header>|null $headers
     * @param array<string, SecurityScheme>|null $securitySchemes
     * @param array<string, Link>|null $links
     * @param array<string, Callbacks>|null $callbacks
     * @param array<string, PathItem>|null $pathItems
     * @param array<string, MediaType>|null $mediaTypes
     */
    public function __construct(
        public ?array $schemas = null,
        public ?array $responses = null,
        public ?array $parameters = null,
        public ?array $examples = null,
        public ?array $requestBodies = null,
        public ?array $headers = null,
        public ?array $securitySchemes = null,
        public ?array $links = null,
        public ?array $callbacks = null,
        public ?array $pathItems = null,
        public ?array $mediaTypes = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('schemas', 'schemas'),
            new FieldMetadata('responses', 'responses'),
            new FieldMetadata('parameters', 'parameters'),
            new FieldMetadata('examples', 'examples'),
            new FieldMetadata('requestBodies', 'requestBodies'),
            new FieldMetadata('headers', 'headers'),
            new FieldMetadata('securitySchemes', 'securitySchemes'),
            new FieldMetadata('links', 'links'),
            new FieldMetadata('callbacks', 'callbacks'),
            new FieldMetadata('pathItems', 'pathItems'),
            new FieldMetadata('mediaTypes', 'mediaTypes'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
