<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Schema\Internal\UriResolver;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_key_exists;
use function is_array;
use function is_string;
use function max;
use function mb_check_encoding;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

final readonly class ExternalRefDetector
{
    public const string CONFINEMENT_KEY_SUFFIX = '_confinement';

    private readonly UriResolver $uriResolver;

    public function __construct(
        private BuilderConfig $config,
    ) {
        $this->uriResolver = new UriResolver();
    }

    public function assertConfinement(): void
    {
        if (null !== $this->config->externalRefAllowedRoot) {
            return;
        }

        $specContent = $this->config->specContent;

        if (null !== $specContent) {
            $this->assertSpecContentConfinement($specContent);
        }

        $this->assertRegisteredDocumentsConfinement();
    }

    private function assertSpecContentConfinement(string $specContent): void
    {
        $cache = $this->config->cache;

        if (null === $cache) {
            $this->assertSpecIsConfined($specContent);

            return;
        }

        $confinementKey = new CacheKeyBuilder($this->config)->forString($specContent)
            . self::CONFINEMENT_KEY_SUFFIX;

        if (null !== $cache->getConfinement($confinementKey)) {
            return;
        }

        $this->assertSpecIsConfined($specContent);
        $cache->setConfinement($confinementKey);
    }

    private function assertRegisteredDocumentsConfinement(): void
    {
        $repository = $this->config->documentRepository;

        if (null === $repository) {
            return;
        }

        foreach ($repository->contents() as $retrievalUri => $content) {
            $this->assertRegisteredDocumentConfinement($retrievalUri, $content);
        }
    }

    private function assertRegisteredDocumentConfinement(string $retrievalUri, string $content): void
    {
        $parsedContent = $this->parseRegisteredContentAsArray($retrievalUri, $content);
        $specDepth = $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH;
        $maxDepth = max($specDepth, ValidationContext::MAX_DEPTH);
        $documentSelf = $this->extractDocumentSelfUri($parsedContent);
        $baseUris = null === $documentSelf ? [] : [$documentSelf];
        $externalRef = $this->detect($parsedContent, 0, $maxDepth, $baseUris);

        if (null !== $externalRef) {
            throw new BuilderException(sprintf(
                'Registered document "%s" contains external $ref "%s" but externalRefAllowedRoot is not set. '
                . 'Call withExternalRefAllowedRoot($path), register the referenced document via withDocument(), '
                . 'or remove the external $ref from the registered document.',
                $retrievalUri,
                $externalRef,
            ));
        }
    }

    /** @param array<array-key, mixed> $data */
    private function extractDocumentSelfUri(array $data): ?string
    {
        /** @var mixed $self */
        $self = $data['$self'] ?? null;

        return is_string($self) && '' !== $self ? $self : null;
    }

    private function assertSpecIsConfined(string $specContent): void
    {
        $parsedSpec = $this->parseSpecContentAsArray($specContent);
        $specDepth = $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH;
        $maxDepth = max($specDepth, ValidationContext::MAX_DEPTH);
        $documentSelf = $this->extractDocumentSelfUri($parsedSpec);
        $baseUris = null === $documentSelf ? [] : [$documentSelf];
        $externalRef = $this->detect($parsedSpec, 0, $maxDepth, $baseUris);

        if (null !== $externalRef) {
            throw new BuilderException(sprintf(
                'Spec contains external $ref "%s" but externalRefAllowedRoot is not set. '
                . 'Call withExternalRefAllowedRoot($path) after fromYamlString/fromJsonString, '
                . 'or remove the external $ref from the spec.',
                $externalRef,
            ));
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $baseUris
     */
    private function detect(array $data, int $depth, int $maxDepth, array $baseUris): ?string
    {
        if ($depth > $maxDepth) {
            return null;
        }

        $directRef = $this->extractExternalRef($data);
        if (null !== $directRef && false === $this->isRepositoryResolvable($directRef, $baseUris)) {
            return $directRef;
        }

        if (array_key_exists('discriminator', $data) && is_array($data['discriminator'])) {
            $discriminatorRef = $this->extractDiscriminatorExternalRef($data['discriminator']);
            if (null !== $discriminatorRef && false === $this->isRepositoryResolvable($discriminatorRef, $baseUris)) {
                return $discriminatorRef;
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->detect($value, $depth + 1, $maxDepth, $baseUris);
            if (null !== $nested) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param list<string> $baseUris
     */
    private function isRepositoryResolvable(string $ref, array $baseUris): bool
    {
        $repository = $this->config->documentRepository;

        if (null === $repository) {
            return false;
        }

        $fragmentPosition = strpos($ref, '#');
        $resourceUri = false === $fragmentPosition ? $ref : substr($ref, 0, $fragmentPosition);

        if (null !== $repository->findByUri($resourceUri)) {
            return true;
        }

        foreach ($baseUris as $baseUri) {
            $absoluteUri = $this->uriResolver->combine($baseUri, $resourceUri);

            if (null !== $repository->findByUri($absoluteUri)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<array-key, mixed> */
    private function parseRegisteredContentAsArray(string $retrievalUri, string $content): array
    {
        return 'yaml' === SpecParserFactory::documentSpecType($retrievalUri)
            ? $this->parseYamlContentAsArray($content)
            : $this->parseJsonContentAsArray($content);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function extractExternalRef(array $data): ?string
    {
        if (!array_key_exists('$ref', $data)) {
            return null;
        }

        /** @var mixed $ref */
        $ref = $data['$ref'];

        if ($this->isExternalRefString($ref)) {
            return $ref;
        }

        return null;
    }

    /** @param array<array-key, mixed> $discriminator */
    private function extractDiscriminatorExternalRef(array $discriminator): ?string
    {
        if (array_key_exists('defaultMapping', $discriminator)) {
            /** @var mixed $defaultMapping */
            $defaultMapping = $discriminator['defaultMapping'];

            if ($this->isExternalRefString($defaultMapping)) {
                return $defaultMapping;
            }
        }

        if (array_key_exists('mapping', $discriminator) && is_array($discriminator['mapping'])) {
            /** @var array<array-key, mixed> $mapping */
            $mapping = $discriminator['mapping'];

            /** @var mixed $mappingRef */
            foreach ($mapping as $mappingRef) {
                if ($this->isExternalRefString($mappingRef)) {
                    return $mappingRef;
                }
            }
        }

        return null;
    }

    /**
     * @psalm-assert-if-true string $ref
     */
    private function isExternalRefString(mixed $ref): bool
    {
        return is_string($ref) && false === str_starts_with($ref, '#');
    }

    /** @return array<array-key, mixed> */
    private function parseSpecContentAsArray(string $content): array
    {
        if ('json' === $this->config->specType) {
            return $this->parseJsonContentAsArray($content);
        }

        return $this->parseYamlContentAsArray(
            $content,
            $this->config->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES,
        );
    }

    /** @return array<array-key, mixed> */
    private function parseJsonContentAsArray(string $content): array
    {
        if (false === mb_check_encoding($content, 'UTF-8')) {
            return [];
        }

        try {
            /** @var mixed $data */
            $data = json_decode($content, true, JsonDepthLimit::Trusted->value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parseYamlContentAsArray(string $content, ?int $specSizeLimit = null): array
    {
        if (null !== $specSizeLimit && strlen($content) > $specSizeLimit) {
            return [];
        }

        try {
            /** @var mixed $data */
            $data = Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
