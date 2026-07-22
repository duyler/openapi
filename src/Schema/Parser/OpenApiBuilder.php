<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Servers;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\Tags;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;
use Throwable;

use function is_array;
use function is_string;
use function sprintf;

use const FILTER_VALIDATE_URL;

/** Abstract base for {@see JsonParser} and {@see YamlParser}. */
abstract class OpenApiBuilder implements SchemaParserInterface
{
    private const string VERSION_PATTERN = '/^3\.[0-2]\.[0-9]+$/';

    protected OpenApiBuildContext $context;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
    ) {
        $this->context = new OpenApiBuildContext($deprecationLogger ?? new DeprecationLogger());
    }

    #[Override]
    final public function parse(string $content): OpenApiDocument
    {
        try {
            $data = $this->parseContent($content);

            if (false === is_array($data)) {
                throw new InvalidSchemaException(
                    sprintf(
                        'Invalid %s: expected object at root, got %s',
                        $this->getFormatName(),
                        TypeFormatter::format($data),
                    ),
                );
            }

            return $this->buildDocument($data);
        } catch (InvalidSchemaException|InvalidUtf8Exception|SpecTooLargeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidSchemaException(
                sprintf('Failed to parse %s: %s', $this->getFormatName(), $e->getMessage()),
                0,
                $e,
            );
        }
    }

    abstract protected function parseContent(string $content): mixed;

    abstract protected function getFormatName(): string;

    final protected function buildDocument(array $data): OpenApiDocument
    {
        $this->validateVersion($data);
        $this->context->documentVersion = (string) $data['openapi'];

        $self = TypeHelper::asStringOrNull($data['$self'] ?? null);
        $this->validateSelfUri($self);

        $infoBuilder = $this->context->infoBuilder;
        $pathItemBuilder = $this->context->pathItemBuilder;
        $componentsBuilder = $this->context->componentsBuilder;

        return new OpenApiDocument(
            openapi: (string) $data['openapi'],
            info: $infoBuilder->buildInfo(TypeHelper::asArray($data['info'])),
            jsonSchemaDialect: TypeHelper::asStringOrNull($data['jsonSchemaDialect'] ?? null),
            servers: isset($data['servers']) ? new Servers($pathItemBuilder->buildServers(TypeHelper::asList($data['servers']))) : null,
            paths: isset($data['paths']) ? $pathItemBuilder->buildPaths(TypeHelper::asArray($data['paths'])) : null,
            webhooks: isset($data['webhooks']) ? $pathItemBuilder->buildWebhooks(TypeHelper::asArray($data['webhooks'])) : null,
            components: isset($data['components']) ? $componentsBuilder->buildComponents(TypeHelper::asArray($data['components'])) : null,
            security: isset($data['security']) ? new SecurityRequirement(TypeHelper::asSecurityListMapOrNull($data['security']) ?? []) : null,
            tags: isset($data['tags']) ? new Tags($infoBuilder->buildTags(TypeHelper::asList($data['tags']))) : null,
            externalDocs: isset($data['externalDocs']) && is_array($data['externalDocs']) ? $infoBuilder->buildExternalDocs(TypeHelper::asArray($data['externalDocs'])) : null,
            self: $self,
        );
    }

    final protected function validateSelfUri(?string $self): void
    {
        if (null === $self) {
            return;
        }

        if (false === filter_var($self, FILTER_VALIDATE_URL)) {
            throw new InvalidSchemaException(sprintf('Invalid $self URI: %s', $self));
        }
    }

    final protected function validateVersion(array $data): void
    {
        if (false === isset($data['openapi'])) {
            throw new InvalidSchemaException('OpenAPI version is required');
        }

        $version = $data['openapi'];
        if (false === is_string($version) || 1 !== preg_match(self::VERSION_PATTERN, $version)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Unsupported OpenAPI version: %s. Only 3.0.x, 3.1.x and 3.2.x are supported.',
                    is_string($version) ? $version : TypeFormatter::format($version),
                ),
            );
        }
    }
}
