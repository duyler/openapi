<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
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
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
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
        private BuilderConfig $config,
    ) {}

    public static function create(): self
    {
        return new self(new BuilderConfig());
    }

    public function fromYamlFile(string $path): self
    {
        return $this->with(new BuilderConfig(specPath: $path, specType: 'yaml'));
    }

    public function fromJsonFile(string $path): self
    {
        return $this->with(new BuilderConfig(specPath: $path, specType: 'json'));
    }

    public function fromYamlString(string $content): self
    {
        return $this->with(new BuilderConfig(specContent: $content, specType: 'yaml'));
    }

    public function fromJsonString(string $content): self
    {
        return $this->with(new BuilderConfig(specContent: $content, specType: 'json'));
    }

    public function withValidatorPool(ValidatorPool $pool): self
    {
        return $this->with(new BuilderConfig(pool: $pool));
    }

    public function withCache(SchemaCache $cache): self
    {
        return $this->with(new BuilderConfig(cache: $cache));
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(new BuilderConfig(logger: $logger));
    }

    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return $this->with(new BuilderConfig(errorFormatter: $formatter));
    }

    public function withFormat(
        string $type,
        string $format,
        FormatValidatorInterface $validator,
    ): self {
        $registry = ($this->config->formatRegistry ?? BuiltinFormats::create())
            ->registerFormat($type, $format, $validator);

        return $this->with(new BuilderConfig(formatRegistry: $registry));
    }

    public function enableCoercion(): self
    {
        return $this->with(new BuilderConfig(coercion: true));
    }

    public function enableNullableAsType(): self
    {
        return $this->with(new BuilderConfig(nullableAsType: true));
    }

    public function disableNullableAsType(): self
    {
        return $this->with(new BuilderConfig(nullableAsType: false));
    }

    public function withEmptyArrayStrategy(EmptyArrayStrategy $strategy): self
    {
        return $this->with(new BuilderConfig(emptyArrayStrategy: $strategy));
    }

    public function withEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        return $this->with(new BuilderConfig(eventDispatcher: $dispatcher));
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
        return $this->with(new BuilderConfig(securityValidation: true));
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
        return $this->with(new BuilderConfig(serverPathResolution: true));
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
        return $this->with(new BuilderConfig(strictFormats: true));
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
        return $this->with(new BuilderConfig(reportDeprecated: true));
    }

    /**
     * Override the maximum allowed size, in bytes, for non-multipart request
     * and response bodies (JSON, XML, text). Bodies larger than this cap are
     * rejected before being fully materialised in memory.
     *
     * @return self New builder instance with the JSON body cap applied
     */
    public function withMaxJsonBodySize(int $maxBytes): self
    {
        return $this->with(new BuilderConfig(maxJsonBodyBytes: $maxBytes));
    }

    /**
     * Override the maximum allowed size, in bytes, for multipart request and
     * response bodies. multipart payloads typically carry larger uploads, so
     * the cap is kept independent from the JSON cap.
     *
     * @return self New builder instance with the multipart body cap applied
     */
    public function withMaxMultipartBodySize(int $maxBytes): self
    {
        return $this->with(new BuilderConfig(maxMultipartBodyBytes: $maxBytes));
    }

    /**
     * Enable strict streaming mode.
     *
     * When enabled, malformed JSON records in NDJSON, SSE, and JSON Text
     * Sequence streams raise a MalformedStreamRecordException instead of being
     * logged and skipped. This is an opt-in behaviour and remains disabled by
     * default for backward compatibility.
     *
     * @return self New builder instance with strict streaming enabled
     */
    public function enableStrictStreaming(): self
    {
        return $this->with(new BuilderConfig(strictStreaming: true));
    }

    /**
     * Disable strict streaming mode.
     *
     * Restores the default fail-open behaviour where malformed JSON records
     * are logged and skipped rather than raised.
     *
     * @return self New builder instance with strict streaming disabled
     */
    public function disableStrictStreaming(): self
    {
        return $this->with(new BuilderConfig(strictStreaming: false));
    }

    /**
     * Override the defensive pcre.backtrack_limit applied to every preg_match
     * call routed through the PregExecutor wrapper. Lowering this cap below the
     * PHP default (1_000_000) bounds the worst-case CPU cost of catastrophic
     * regular expressions on attacker-controlled input such as JSON-Schema
     * "pattern" fields.
     *
     * @return self New builder instance with the regex backtracking cap applied
     */
    public function withMaxRegexBacktracks(int $maxBacktracks): self
    {
        return $this->with(new BuilderConfig(maxRegexBacktracks: $maxBacktracks));
    }

    public function build(): OpenApiValidatorInterface
    {
        $document = $this->loadSpec();

        $maxRegexBacktracks = $this->config->maxRegexBacktracks ?? ValidatorConfiguration::DEFAULT_MAX_REGEX_BACKTRACKS;
        $pregExecutor = new PregExecutor($maxRegexBacktracks);
        $pool = $this->config->pool ?? new ValidatorPool();
        $formatRegistry = $this->config->formatRegistry ?? BuiltinFormats::create($pregExecutor);
        $errorFormatter = $this->config->errorFormatter ?? new SimpleFormatter();
        $pathRegexCache = new PathRegexCache();
        $regexValidator = new RegexValidator();
        $pathFinder = new PathFinder($document, $pathRegexCache);
        $logger = $this->config->logger ?? new NullLogger();
        $refResolver = new RefResolver();

        $coercion = $this->config->coercion ?? false;
        $nullableAsType = $this->config->nullableAsType ?? true;
        $emptyArrayStrategy = $this->config->emptyArrayStrategy ?? EmptyArrayStrategy::AllowBoth;
        $securityValidation = $this->config->securityValidation ?? false;
        $strictFormats = $this->config->strictFormats ?? false;
        $reportDeprecated = $this->config->reportDeprecated ?? true;
        $maxJsonBodyBytes = $this->config->maxJsonBodyBytes ?? ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES;
        $maxMultipartBodyBytes = $this->config->maxMultipartBodyBytes ?? ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES;
        $strictStreaming = $this->config->strictStreaming ?? false;

        $context = new ValidationContext(
            document: $document,
            pool: $pool,
            formatRegistry: $formatRegistry,
            errorFormatter: $errorFormatter,
            refResolver: $refResolver,
            coercion: $coercion,
            nullableAsType: $nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy,
            reportDeprecated: $reportDeprecated,
            logger: $logger,
            eventDispatcher: $this->config->eventDispatcher,
            strictFormats: $strictFormats,
            pathRegexCache: $pathRegexCache,
            regexValidator: $regexValidator,
            maxJsonBodyBytes: $maxJsonBodyBytes,
            maxMultipartBodyBytes: $maxMultipartBodyBytes,
            strictStreaming: $strictStreaming,
            maxRegexBacktracks: $maxRegexBacktracks,
            pregExecutor: $pregExecutor,
        );

        return new OpenApiValidator(
            document: $document,
            configuration: new ValidatorConfiguration(
                coercion: $coercion,
                nullableAsType: $nullableAsType,
                emptyArrayStrategy: $emptyArrayStrategy,
                securityValidation: $securityValidation,
                strictFormats: $strictFormats,
                reportDeprecated: $reportDeprecated,
                maxJsonBodyBytes: $maxJsonBodyBytes,
                maxMultipartBodyBytes: $maxMultipartBodyBytes,
                strictStreaming: $strictStreaming,
                maxRegexBacktracks: $maxRegexBacktracks,
            ),
            dependencies: new ValidatorDependencies(
                pool: $pool,
                formatRegistry: $formatRegistry,
                errorFormatter: $errorFormatter,
                logger: $logger,
                refResolver: $refResolver,
                pathFinder: $pathFinder,
                linkResolver: new LinkResolver(),
                requestValidation: new RequestValidationHandler(
                    $context,
                    $pathFinder,
                    $securityValidation,
                    $this->config->serverPathResolution ?? false,
                ),
                responseValidation: new ResponseValidationHandler($context),
                schemaValidation: new SchemaValidatorAdapter($context),
                webhookValidation: new WebhookValidator($context, $securityValidation),
                callbackValidation: new CallbackValidator($context, $securityValidation),
                pathRegexCache: $pathRegexCache,
                regexValidator: $regexValidator,
                cache: $this->config->cache,
                eventDispatcher: $this->config->eventDispatcher,
            ),
        );
    }

    private function with(BuilderConfig $overrides): self
    {
        return new self($this->config->merge($overrides));
    }

    private function loadSpec(): OpenApiDocument
    {
        if (null !== $this->config->specPath) {
            return $this->loadSpecFromFile();
        }

        if (null !== $this->config->specContent) {
            return $this->loadSpecFromString();
        }

        throw new BuilderException(
            'Spec not loaded. Call fromYamlFile(), fromJsonFile(), fromYamlString(), or fromJsonString() first.',
        );
    }

    private function loadSpecFromFile(): OpenApiDocument
    {
        if (null === $this->config->specPath || null === $this->config->specType) {
            throw new BuilderException('Spec path or type not set');
        }

        $cacheKey = $this->generateCacheKeyFromFile($this->config->specPath);

        if (null !== $this->config->cache) {
            $cachedDocument = $this->config->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        if (false === is_file($this->config->specPath)) {
            throw new BuilderException(sprintf('Spec file does not exist: %s', $this->config->specPath));
        }

        $content = file_get_contents($this->config->specPath);

        if (false === $content) {
            throw new BuilderException(sprintf('Failed to read spec file: %s', $this->config->specPath));
        }

        $document = $this->parseSpec($content);

        if (null !== $this->config->cache) {
            $this->config->cache->set($cacheKey, $document);
        }

        return $document;
    }

    private function loadSpecFromString(): OpenApiDocument
    {
        if (null === $this->config->specContent || null === $this->config->specType) {
            throw new BuilderException('Spec content or type not set');
        }

        $cacheKey = $this->generateCacheKeyFromString($this->config->specContent);

        if (null !== $this->config->cache) {
            $cachedDocument = $this->config->cache->get($cacheKey);
            if (null !== $cachedDocument) {
                return $cachedDocument;
            }
        }

        $document = $this->parseSpec($this->config->specContent);

        if (null !== $this->config->cache) {
            $this->config->cache->set($cacheKey, $document);
        }

        return $document;
    }

    private function parseSpec(string $content): OpenApiDocument
    {
        try {
            $deprecationLogger = new DeprecationLogger($this->config->logger ?? new NullLogger(), $this->config->reportDeprecated ?? true);

            if ('yaml' === $this->config->specType) {
                $parser = new YamlParser($deprecationLogger);

                return $parser->parse($content);
            }

            if ('json' === $this->config->specType) {
                $parser = new JsonParser($deprecationLogger);

                return $parser->parse($content);
            }

            throw new BuilderException(sprintf('Unsupported spec type: %s', $this->config->specType ?? 'none'));
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
