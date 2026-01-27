<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\ValidatorPool;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

use function sprintf;

/**
 * Fluent builder for creating OpenApiValidator instances.
 *
 * Provides a convenient interface for configuring and building validators
 * with support for caching, custom formats, error formatting, and event dispatching.
 */
class OpenApiValidatorBuilder
{
    protected function __construct(
        protected readonly ?string $specPath = null,
        protected readonly ?string $specContent = null,
        protected readonly ?string $specType = null,
        protected readonly ?ValidatorPool $pool = null,
        protected readonly ?SchemaCache $cache = null,
        protected readonly ?object $logger = null,
        protected readonly ?FormatRegistry $formatRegistry = null,
        protected readonly bool $coercion = false,
        protected readonly bool $nullableAsType = true,
        protected readonly ?ErrorFormatterInterface $errorFormatter = null,
        protected readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * Create a new builder instance.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Load OpenAPI spec from YAML file.
     *
     * @param string $path Path to the YAML file
     * @return self
     *
     * @example
     * $validator = OpenApiValidatorBuilder::create()
     *     ->fromYamlFile('openapi.yaml')
     *     ->build();
     */
    public function fromYamlFile(string $path): self
    {
        return new self(
            specPath: $path,
            specType: 'yaml',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Load OpenAPI spec from JSON file
     */
    public function fromJsonFile(string $path): self
    {
        return new self(
            specPath: $path,
            specType: 'json',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Load OpenAPI spec from YAML string
     */
    public function fromYamlString(string $content): self
    {
        return new self(
            specContent: $content,
            specType: 'yaml',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Load OpenAPI spec from JSON string
     */
    public function fromJsonString(string $content): self
    {
        return new self(
            specContent: $content,
            specType: 'json',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Set custom validator pool
     */
    public function withValidatorPool(ValidatorPool $pool): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Enable PSR-6 caching for OpenAPI documents.
     *
     * @param SchemaCache $cache PSR-6 cache implementation
     * @return self
     *
     * @example
     * $cache = new SchemaCache($symfonyCacheAdapter);
     * $validator = OpenApiValidatorBuilder::create()
     *     ->fromYamlFile('openapi.yaml')
     *     ->withCache($cache)
     *     ->build();
     */
    public function withCache(SchemaCache $cache): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Set PSR-3 logger
     */
    public function withLogger(object $logger): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Set error formatter
     */
    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $formatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Register custom format validator
     */
    public function withFormat(
        string $type,
        string $format,
        FormatValidatorInterface $validator,
    ): self {
        $registry = $this->formatRegistry ?? BuiltinFormats::create();
        $registry = $registry->registerFormat($type, $format, $validator);

        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $registry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Enable type coercion
     */
    public function enableCoercion(): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: true,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Enable nullable validation
     */
    public function enableNullableAsType(): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: true,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Disable nullable validation
     */
    public function disableNullableAsType(): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: false,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    /**
     * Set PSR-14 event dispatcher.
     *
     * @param EventDispatcherInterface $dispatcher PSR-14 event dispatcher
     * @return self
     *
     * @example
     * $validator = OpenApiValidatorBuilder::create()
     *     ->fromYamlFile('openapi.yaml')
     *     ->withEventDispatcher($symfonyEventDispatcher)
     *     ->build();
     */
    public function withEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $dispatcher,
        );
    }

    /**
     * Build the validator instance.
     *
     * @return OpenApiValidator Configured validator instance
     * @throws BuilderException If spec is not loaded
     *
     * @example
     * $validator = OpenApiValidatorBuilder::create()
     *     ->fromYamlFile('openapi.yaml')
     *     ->withCache($cache)
     *     ->withEventDispatcher($dispatcher)
     *     ->enableCoercion()
     *     ->build();
     */
    public function build(): OpenApiValidator
    {
        $document = $this->loadSpec();

        $pool = $this->pool ?? new ValidatorPool();
        $formatRegistry = $this->formatRegistry ?? BuiltinFormats::create();
        $errorFormatter = $this->errorFormatter ?? new SimpleFormatter();
        $pathFinder = new PathFinder($document);

        return new OpenApiValidator(
            document: $document,
            pool: $pool,
            formatRegistry: $formatRegistry,
            errorFormatter: $errorFormatter,
            cache: $this->cache,
            logger: $this->logger,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            eventDispatcher: $this->eventDispatcher,
            pathFinder: $pathFinder,
        );
    }

    /**
     * @throws BuilderException
     */
    private function loadSpec(): OpenApiDocument
    {
        if (null !== $this->specPath) {
            return $this->loadSpecFromFile();
        }

        if (null !== $this->specContent) {
            return $this->loadSpecFromString();
        }

        throw new BuilderException(
            'Spec not loaded. Call fromYamlFile(), fromJsonFile(), fromYamlString(), or fromJsonString() first.',
        );
    }

    /**
     * @throws BuilderException
     */
    private function loadSpecFromFile(): OpenApiDocument
    {
        if (null === $this->specPath || null === $this->specType) {
            throw new BuilderException('Spec path or type not set');
        }

        $cacheKey = $this->generateCacheKeyFromFile($this->specPath);

        if (null !== $this->cache) {
            $cachedDocument = $this->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        if (false === is_file($this->specPath)) {
            throw new BuilderException(sprintf('Spec file does not exist: %s', $this->specPath));
        }

        $content = file_get_contents($this->specPath);

        if (false === $content) {
            throw new BuilderException(sprintf('Failed to read spec file: %s', $this->specPath));
        }

        $document = $this->parseSpec($content);

        if (null !== $this->cache) {
            $this->cache->set($cacheKey, $document);
        }

        return $document;
    }

    /**
     * @throws BuilderException
     */
    private function loadSpecFromString(): OpenApiDocument
    {
        if (null === $this->specContent || null === $this->specType) {
            throw new BuilderException('Spec content or type not set');
        }

        $cacheKey = $this->generateCacheKeyFromString($this->specContent);

        if (null !== $this->cache) {
            $cachedDocument = $this->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        $document = $this->parseSpec($this->specContent);

        if (null !== $this->cache) {
            $this->cache->set($cacheKey, $document);
        }

        return $document;
    }

    /**
     * @throws BuilderException
     */
    private function parseSpec(string $content): OpenApiDocument
    {
        try {
            if ('yaml' === $this->specType) {
                $parser = new YamlParser();

                return $parser->parse($content);
            }

            if ('json' === $this->specType) {
                $parser = new JsonParser();

                return $parser->parse($content);
            }

            throw new BuilderException(sprintf('Unsupported spec type: %s', $this->specType ?? 'none'));
        } catch (BuilderException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new BuilderException(
                sprintf('Failed to parse spec: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    private function generateCacheKeyFromFile(string $path): string
    {
        $realPath = realpath($path);

        if (false === $realPath) {
            return 'openapi_spec_file_' . md5($path);
        }

        return 'openapi_spec_file_' . md5($realPath);
    }

    private function generateCacheKeyFromString(string $content): string
    {
        return 'openapi_spec_content_' . md5($content);
    }
}
