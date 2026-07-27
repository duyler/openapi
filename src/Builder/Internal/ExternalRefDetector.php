<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\JsonDepthLimit;
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

use const JSON_THROW_ON_ERROR;

/**
 * Fail-closed confinement guard for external `$ref` references in
 * specs loaded via `fromYamlString()` / `fromJsonString()`.
 *
 * String-loaded specs have no auto-derived `externalRefAllowedRoot`, so the
 * builtin `FileExternalRefResolver` would otherwise run with `allowedRoot =
 * null` and silently accept any `file://...` or relative-path `$ref`. This
 * collaborator scans the raw spec before build() proceeds and rejects any
 * external `$ref` (or discriminator mapping/defaultMapping pointing outside
 * the document) when `externalRefAllowedRoot` has not been set.
 *
 * File-loaded specs skip the scan: their `externalRefAllowedRoot` is auto
 * derived from `dirname(realpath($path))` and the `FileExternalRefResolver`
 * enforces the boundary at resolution time.
 */
final readonly class ExternalRefDetector
{
    public function __construct(
        private BuilderConfig $config,
    ) {}

    public function assertConfinement(): void
    {
        if (null !== $this->config->externalRefAllowedRoot) {
            return;
        }

        if (null === $this->config->specContent) {
            return;
        }

        $parsedSpec = $this->parseSpecContentAsArray($this->config->specContent);
        $specDepth = $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH;
        $maxDepth = max($specDepth, ValidationContext::MAX_DEPTH);
        $externalRef = $this->detect($parsedSpec, 0, $maxDepth);

        if (null !== $externalRef) {
            throw new BuilderException(sprintf(
                'Spec contains external $ref "%s" but externalRefAllowedRoot is not set. '
                . 'Call withExternalRefAllowedRoot($path) after fromYamlString/fromJsonString, '
                . 'or remove the external $ref from the spec.',
                $externalRef,
            ));
        }
    }

    /** @param array<array-key, mixed> $data */
    private function detect(array $data, int $depth, int $maxDepth): ?string
    {
        if ($depth > $maxDepth) {
            return null;
        }

        $directRef = $this->extractExternalRef($data);
        if (null !== $directRef) {
            return $directRef;
        }

        if (array_key_exists('discriminator', $data) && is_array($data['discriminator'])) {
            $discriminatorRef = $this->extractDiscriminatorExternalRef($data['discriminator']);
            if (null !== $discriminatorRef) {
                return $discriminatorRef;
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->detect($value, $depth + 1, $maxDepth);
            if (null !== $nested) {
                return $nested;
            }
        }

        return null;
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

        if (is_string($ref) && !str_starts_with($ref, '#/')) {
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

            if (is_string($defaultMapping) && !str_starts_with($defaultMapping, '#/')) {
                return $defaultMapping;
            }
        }

        if (array_key_exists('mapping', $discriminator) && is_array($discriminator['mapping'])) {
            /** @var array<array-key, mixed> $mapping */
            $mapping = $discriminator['mapping'];

            foreach ($mapping as $mappingRef) {
                /** @var mixed $mappingRef */
                if (!is_string($mappingRef)) {
                    continue;
                }

                if (!str_starts_with($mappingRef, '#/')) {
                    return $mappingRef;
                }
            }
        }

        return null;
    }

    /** @return array<array-key, mixed> */
    private function parseSpecContentAsArray(string $content): array
    {
        if ('json' === $this->config->specType) {
            return $this->parseJsonContentAsArray($content);
        }

        return $this->parseYamlContentAsArray($content);
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

    /** @return array<array-key, mixed> */
    private function parseYamlContentAsArray(string $content): array
    {
        $maxBytes = $this->config->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES;
        if (strlen($content) > $maxBytes) {
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
