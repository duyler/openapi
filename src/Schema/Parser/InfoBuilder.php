<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Tag;

use function array_map;
use function array_values;
use function is_array;

/**
 * Builds OpenAPI Info / Contact / License / ExternalDocs / Tag objects.
 *
 * Group: identity and descriptive metadata that does not depend on schemas,
 * parameters, or components.
 */
final readonly class InfoBuilder
{
    public function __construct(private OpenApiBuildContext $context) {}

    public function buildInfo(array $data): InfoObject
    {
        if (false === isset($data['title']) || false === isset($data['version'])) {
            throw new InvalidSchemaException('Info object must have title and version');
        }

        return new InfoObject(
            title: TypeHelper::asString($data['title']),
            version: TypeHelper::asString($data['version']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            termsOfService: TypeHelper::asStringOrNull($data['termsOfService'] ?? null),
            contact: isset($data['contact']) ? $this->buildContact(TypeHelper::asArray($data['contact'])) : null,
            license: isset($data['license']) ? $this->buildLicense(TypeHelper::asArray($data['license'])) : null,
        );
    }

    public function buildContact(array $data): Contact
    {
        return new Contact(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            url: TypeHelper::asStringOrNull($data['url'] ?? null),
            email: TypeHelper::asStringOrNull($data['email'] ?? null),
        );
    }

    public function buildLicense(array $data): License
    {
        return new License(
            name: TypeHelper::asString($data['name']),
            identifier: TypeHelper::asStringOrNull($data['identifier'] ?? null),
            url: TypeHelper::asStringOrNull($data['url'] ?? null),
        );
    }

    public function buildExternalDocs(array $data): ExternalDocs
    {
        if (false === isset($data['url'])) {
            throw new InvalidSchemaException('External documentation must have url');
        }

        return new ExternalDocs(
            url: TypeHelper::asString($data['url']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
        );
    }

    public function buildTag(array $data): Tag
    {
        return new Tag(
            name: TypeHelper::asString($data['name']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            externalDocs: isset($data['externalDocs']) && is_array($data['externalDocs'])
                ? $this->buildExternalDocs(TypeHelper::asArray($data['externalDocs']))
                : null,
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            parent: TypeHelper::asStringOrNull($data['parent'] ?? null),
            kind: TypeHelper::asStringOrNull($data['kind'] ?? null),
        );
    }

    /**
     * @return list<Tag>
     */
    public function buildTags(array $data): array
    {
        return array_map(fn(array $tag) => $this->buildTag($tag), array_values($data));
    }
}
