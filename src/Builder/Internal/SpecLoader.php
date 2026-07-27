<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Exception;
use Psr\Log\NullLogger;

use function file_get_contents;
use function is_file;
use function sprintf;

final readonly class SpecLoader
{
    public function __construct(
        private BuilderConfig $config,
        private CacheKeyBuilder $cacheKeyBuilder,
    ) {}

    public function load(): OpenApiDocument
    {
        if (null !== $this->config->specPath) {
            return $this->loadFromFile();
        }

        if (null !== $this->config->specContent) {
            return $this->loadFromString();
        }

        throw new BuilderException(
            'Spec not loaded. Call fromYamlFile(), fromJsonFile(), fromYamlString(), or fromJsonString() first.',
        );
    }

    private function loadFromFile(): OpenApiDocument
    {
        $specPath = $this->config->specPath;
        $specType = $this->config->specType;

        if (null === $specPath || null === $specType) {
            throw new BuilderException('Spec path or type not set');
        }

        if (false === is_file($specPath)) {
            throw new BuilderException(sprintf('Spec file does not exist: %s', $specPath));
        }

        $content = file_get_contents($specPath);

        if (false === $content) {
            throw new BuilderException(sprintf('Failed to read spec file: %s', $specPath));
        }

        $cacheKey = $this->cacheKeyBuilder->forFile($specPath, $content);

        if (null !== $this->config->cache) {
            $cachedDocument = $this->config->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        $document = $this->parseSpec($content);

        if (null !== $this->config->cache) {
            $this->config->cache->set($cacheKey, $document);
        }

        return $document;
    }

    private function loadFromString(): OpenApiDocument
    {
        $specContent = $this->config->specContent;
        $specType = $this->config->specType;

        if (null === $specContent || null === $specType) {
            throw new BuilderException('Spec content or type not set');
        }

        $cacheKey = $this->cacheKeyBuilder->forString($specContent);

        if (null !== $this->config->cache) {
            $cachedDocument = $this->config->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        $document = $this->parseSpec($specContent);

        if (null !== $this->config->cache) {
            $this->config->cache->set($cacheKey, $document);
        }

        return $document;
    }

    private function parseSpec(string $content): OpenApiDocument
    {
        try {
            $deprecationLogger = new DeprecationLogger(
                $this->config->logger ?? new NullLogger(),
                $this->config->reportDeprecated ?? true,
            );

            $parser = match ($this->config->specType) {
                'yaml' => new YamlParser(
                    $deprecationLogger,
                    $this->config->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES,
                    $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH,
                ),
                'json' => new JsonParser(
                    $deprecationLogger,
                    $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH,
                    $this->config->maxSpecSizeBytes ?? JsonParser::DEFAULT_MAX_SPEC_BYTES,
                ),
                default => throw new BuilderException(
                    sprintf('Unsupported spec type: %s', $this->config->specType ?? 'none'),
                ),
            };

            return $parser->parse($content);
        } catch (InvalidUtf8Exception|SpecTooLargeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new BuilderException(
                sprintf('Failed to parse spec: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }
}
