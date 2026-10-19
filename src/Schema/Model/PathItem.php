<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

use function strtolower;

final readonly class PathItem implements JsonSerializable
{
    /**
     * @param array<string, Operation>|null $additionalOperations
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?Operation $get = null,
        public ?Operation $put = null,
        public ?Operation $post = null,
        public ?Operation $delete = null,
        public ?Operation $options = null,
        public ?Operation $head = null,
        public ?Operation $patch = null,
        public ?Operation $trace = null,
        public ?Operation $query = null,
        public ?array $additionalOperations = null,
        public ?Servers $servers = null,
        public ?Parameters $parameters = null,
    ) {}

    public function getOperation(string $method): ?Operation
    {
        return match (strtolower($method)) {
            'get' => $this->get,
            'put' => $this->put,
            'post' => $this->post,
            'delete' => $this->delete,
            'options' => $this->options,
            'head' => $this->head,
            'patch' => $this->patch,
            'trace' => $this->trace,
            'query' => $this->query,
            default => null,
        };
    }

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('ref', '$ref'),
            new FieldMetadata('summary', 'summary'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('get', 'get'),
            new FieldMetadata('put', 'put'),
            new FieldMetadata('post', 'post'),
            new FieldMetadata('delete', 'delete'),
            new FieldMetadata('options', 'options'),
            new FieldMetadata('head', 'head'),
            new FieldMetadata('patch', 'patch'),
            new FieldMetadata('trace', 'trace'),
            new FieldMetadata('query', 'query'),
            new FieldMetadata('additionalOperations', 'additionalOperations'),
            new FieldMetadata('servers', 'servers'),
            new FieldMetadata('parameters', 'parameters'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
