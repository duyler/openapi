<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\SchemaValidatorAdapter;
use Duyler\OpenApi\Validator\Validation\ValidationContext;
use Duyler\OpenApi\Validator\Validation\WebhookValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;

final readonly class OpenApiValidatorBuilder
{
    private const string CACHE_KEY_FILE_PREFIX = 'openapi_spec_file_';
    private const string CACHE_KEY_CONTENT_PREFIX = 'openapi_spec_content_';

    private function __construct(
        private ?string $specPath = null,
        private ?string $specContent = null,
        private ?string $specType = null,
        private ?ValidatorPool $pool = null,
        private ?SchemaCache $cache = null,
        private ?LoggerInterface $logger = null,
        private ?FormatRegistry $formatRegistry = null,
        private bool $coercion = false,
        private bool $nullableAsType = true,
        private EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private ?ErrorFormatterInterface $errorFormatter = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private bool $securityValidation = false,
        private bool $serverPathResolution = false,
        private bool $strictFormats = false,
        private bool $reportDeprecated = true,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    public function fromYamlFile(string $path): self
    {
        return $this->with(specPath: $path, specType: 'yaml');
    }

    public function fromJsonFile(string $path): self
    {
        return $this->with(specPath: $path, specType: 'json');
    }

    public function fromYamlString(string $content): self
    {
        return $this->with(specContent: $content, specType: 'yaml');
    }

    public function fromJsonString(string $content): self
    {
        return $this->with(specContent: $content, specType: 'json');
    }

    public function withValidatorPool(ValidatorPool $pool): self
    {
        return $this->with(pool: $pool);
    }

    public function withCache(SchemaCache $cache): self
    {
        return $this->with(cache: $cache);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(logger: $logger);
    }

    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return $this->with(errorFormatter: $formatter);
    }

    public function withFormat(
        string $type,
        string $format,
        FormatValidatorInterface $validator,
    ): self {
        $registry = ($this->formatRegistry ?? BuiltinFormats::create())
            ->registerFormat($type, $format, $validator);

        return $this->with(formatRegistry: $registry);
    }

    public function enableCoercion(): self
    {
        return $this->with(coercion: true);
    }

    public function enableNullableAsType(): self
    {
        return $this->with(nullableAsType: true);
    }

    public function disableNullableAsType(): self
    {
        return $this->with(nullableAsType: false);
    }

    public function withEmptyArrayStrategy(EmptyArrayStrategy $strategy): self
    {
        return $this->with(emptyArrayStrategy: $strategy);
    }

    public function withEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        return $this->with(eventDispatcher: $dispatcher);
    }

    /**
     * Enable security scheme validation for requests
     *
     * When enabled, the validator checks that required security credentials
     * are present in the request headers, query parameters, or cookies.
     *
     * @return self New builder instance with security validation enabled
     */
    public function enableSecurityValidation(): self
    {
        return $this->with(securityValidation: true);
    }

    /**
     * Enable server path resolution for request validation
     *
     * When enabled, the validator strips the base path defined in server URLs
     * from the request path before matching against OpenAPI path templates.
     * This allows validation of requests routed through a reverse proxy
     * that prefixes paths with a version segment (e.g., /v1/users).
     *
     * @return self New builder instance with server path resolution enabled
     */
    public function enableServerPathResolution(): self
    {
        return $this->with(serverPathResolution: true);
    }

    /**
     * Enable strict format validation
     *
     * When enabled, unknown format values are rejected during validation
     * instead of being silently skipped.
     *
     * @return self New builder instance with strict formats enabled
     */
    public function enableStrictFormats(): self
    {
        return $this->with(strictFormats: true);
    }

    /**
     * Enable reporting of deprecated schema elements
     *
     * When enabled, deprecated fields in the OpenAPI specification are logged
     * through the configured PSR-3 logger.
     *
     * @return self New builder instance with deprecation reporting enabled
     */
    public function enableReportDeprecated(): self
    {
        return $this->with(reportDeprecated: true);
    }

    public function build(): OpenApiValidatorInterface
    {
        $document = $this->loadSpec();

        $pool = $this->pool ?? new ValidatorPool();
        $formatRegistry = $this->formatRegistry ?? BuiltinFormats::create();
        $errorFormatter = $this->errorFormatter ?? SimpleFormatter::shared();
        $pathFinder = new PathFinder($document);
        $logger = $this->logger ?? new NullLogger();
        $refResolver = new RefResolver();

        $context = new ValidationContext(
            document: $document,
            pool: $pool,
            formatRegistry: $formatRegistry,
            errorFormatter: $errorFormatter,
            refResolver: $refResolver,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            reportDeprecated: $this->reportDeprecated,
            logger: $logger,
            eventDispatcher: $this->eventDispatcher,
            strictFormats: $this->strictFormats,
        );

        return new OpenApiValidator(
            document: $document,
            pool: $pool,
            formatRegistry: $formatRegistry,
            errorFormatter: $errorFormatter,
            cache: $this->cache,
            logger: $logger,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            eventDispatcher: $this->eventDispatcher,
            pathFinder: $pathFinder,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
            refResolver: $refResolver,
            validationContext: $context,
            requestValidationHandler: new RequestValidationHandler(
                $context,
                $pathFinder,
                $this->securityValidation,
                $this->serverPathResolution,
            ),
            responseValidationHandler: new ResponseValidationHandler($context),
            schemaValidatorAdapter: new SchemaValidatorAdapter($context),
            webhookValidator: new WebhookValidator($context),
            callbackValidator: new CallbackValidator($context),
            linkResolver: new LinkResolver(),
        );
    }

    private function with(
        ?string $specPath = null,
        ?string $specContent = null,
        ?string $specType = null,
        ?ValidatorPool $pool = null,
        ?SchemaCache $cache = null,
        ?LoggerInterface $logger = null,
        ?FormatRegistry $formatRegistry = null,
        ?bool $coercion = null,
        ?bool $nullableAsType = null,
        ?EmptyArrayStrategy $emptyArrayStrategy = null,
        ?ErrorFormatterInterface $errorFormatter = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?bool $securityValidation = null,
        ?bool $serverPathResolution = null,
        ?bool $strictFormats = null,
        ?bool $reportDeprecated = null,
    ): self {
        return new self(
            specPath: $specPath ?? $this->specPath,
            specContent: $specContent ?? $this->specContent,
            specType: $specType ?? $this->specType,
            pool: $pool ?? $this->pool,
            cache: $cache ?? $this->cache,
            logger: $logger ?? $this->logger,
            formatRegistry: $formatRegistry ?? $this->formatRegistry,
            coercion: $coercion ?? $this->coercion,
            nullableAsType: $nullableAsType ?? $this->nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy ?? $this->emptyArrayStrategy,
            errorFormatter: $errorFormatter ?? $this->errorFormatter,
            eventDispatcher: $eventDispatcher ?? $this->eventDispatcher,
            securityValidation: $securityValidation ?? $this->securityValidation,
            serverPathResolution: $serverPathResolution ?? $this->serverPathResolution,
            strictFormats: $strictFormats ?? $this->strictFormats,
            reportDeprecated: $reportDeprecated ?? $this->reportDeprecated,
        );
    }

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

    private function parseSpec(string $content): OpenApiDocument
    {
        try {
            $deprecationLogger = new DeprecationLogger($this->logger ?? new NullLogger(), $this->reportDeprecated);

            if ('yaml' === $this->specType) {
                $parser = new YamlParser($deprecationLogger);

                return $parser->parse($content);
            }

            if ('json' === $this->specType) {
                $parser = new JsonParser($deprecationLogger);

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
            return self::CACHE_KEY_FILE_PREFIX . hash('xxh64', $path);
        }

        return self::CACHE_KEY_FILE_PREFIX . hash('xxh64', $realPath);
    }

    private function generateCacheKeyFromString(string $content): string
    {
        return self::CACHE_KEY_CONTENT_PREFIX . hash('xxh64', $content);
    }
}
