<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\Internal\CacheKeyBuilder;
use Duyler\OpenApi\Builder\Internal\ExternalRefDetector;
use Duyler\OpenApi\Builder\Internal\SpecLoader;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Validation\Internal\BodyLimits;
use Duyler\OpenApi\Validator\Validation\Internal\RootServices;
use Duyler\OpenApi\Validator\Validation\Internal\ValidationGroup;
use Duyler\OpenApi\Validator\Validation\Internal\ValidatorOptions;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\SchemaValidatorAdapter;
use Duyler\OpenApi\Validator\Validation\ValidationStack;
use Duyler\OpenApi\Validator\Validation\WebhookValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Deprecated;

use function dirname;
use function realpath;
use function sprintf;

final readonly class OpenApiValidatorBuilder
{
    private function __construct(
        private BuilderConfig $config,
    ) {}

    public static function create(): self
    {
        return new self(new BuilderConfig());
    }

    public function fromYamlFile(string $path): self
    {
        $realPath = $this->resolveSpecPath($path);

        return $this->with(new BuilderConfig(
            specPath: $realPath,
            specType: 'yaml',
            externalRefAllowedRoot: dirname($realPath),
        ));
    }

    public function fromJsonFile(string $path): self
    {
        $realPath = $this->resolveSpecPath($path);

        return $this->with(new BuilderConfig(
            specPath: $realPath,
            specType: 'json',
            externalRefAllowedRoot: dirname($realPath),
        ));
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

    public function withSecurityVerboseLogging(LoggerInterface $logger): self
    {
        return $this->with(new BuilderConfig(securityVerboseLogger: $logger));
    }

    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return $this->with(new BuilderConfig(errorFormatter: $formatter));
    }

    public function withDetailedErrors(bool $includeSensitive = false): self
    {
        return $this->with(new BuilderConfig(
            errorFormatter: new DetailedFormatter(includeSensitiveValues: $includeSensitive),
        ));
    }

    public function withFormat(
        string $type,
        string $format,
        FormatValidatorInterface $validator,
    ): self {
        $userRegistry = $this->config->formatRegistry ?? new FormatRegistry();
        $registry = $userRegistry->registerFormat($type, $format, $validator);

        return $this->with(new BuilderConfig(formatRegistry: $registry));
    }

    public function enableCoercion(): self
    {
        return $this->with(new BuilderConfig(coercion: true));
    }

    public function disableStrictCoercion(): self
    {
        return $this->with(new BuilderConfig(strictCoercion: false));
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

    public function withExternalRefAllowedRoot(string $path): self
    {
        $realPath = realpath($path);
        if (false === $realPath) {
            throw new BuilderException(sprintf('External ref allowed root does not exist: %s', $path));
        }

        return $this->with(new BuilderConfig(externalRefAllowedRoot: $realPath));
    }

    public function enableSecurityValidation(): self
    {
        return $this->with(new BuilderConfig(securityValidation: true));
    }

    public function enableServerPathResolution(): self
    {
        return $this->with(new BuilderConfig(serverPathResolution: true));
    }

    public function enableStrictFormats(): self
    {
        return $this->with(new BuilderConfig(strictFormats: true));
    }

    public function enableReportDeprecated(): self
    {
        return $this->with(new BuilderConfig(reportDeprecated: true));
    }

    public function withMaxJsonBodySize(int $maxBytes): self
    {
        return $this->with(new BuilderConfig(maxJsonBodyBytes: $maxBytes));
    }

    public function withMaxMultipartBodySize(int $maxBytes): self
    {
        return $this->with(new BuilderConfig(maxMultipartBodyBytes: $maxBytes));
    }

    public function enableStrictStreaming(): self
    {
        return $this->with(new BuilderConfig(strictStreaming: true));
    }

    public function disableStrictStreaming(): self
    {
        return $this->with(new BuilderConfig(strictStreaming: false));
    }

    public function withMaxRegexBacktracks(int $maxBacktracks): self
    {
        return $this->with(new BuilderConfig(maxRegexBacktracks: $maxBacktracks));
    }

    #[Deprecated(message: <<<'TXT'
    since 1.x, will be removed in 2.0. Strict mode is now the
                 default: callback expressions that use runtime templates
                 such as `{$request.body#/callback_url}` throw an
                 {@see \Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException} by default instead
                 of being treated as wildcards that accept any URL. This
                 method is retained as a no-op for backward compatibility
                 with callers that explicitly opted in. To restore the
                 legacy wildcard behaviour, use
                 {@see disableStrictCallbackRuntimeTemplate()}.
    TXT)]
    public function enableStrictCallbackRuntimeTemplate(): self
    {
        return $this;
    }

    public function disableStrictCallbackRuntimeTemplate(): self
    {
        return $this->with(new BuilderConfig(strictCallbackRuntimeTemplate: false));
    }

    /**
     * @param int $bytes
     */
    public function withExternalRefMaxBytes(int $bytes): self
    {
        $this->assertPositiveInteger($bytes, 'External ref max bytes must be a positive integer');

        return $this->with(new BuilderConfig(externalRefMaxBytes: $bytes));
    }

    /**
     * @param int $max
     */
    public function withMaxStreamingRecords(int $max): self
    {
        $this->assertPositiveInteger($max, 'Max streaming records must be a positive integer');

        return $this->with(new BuilderConfig(maxStreamingRecords: $max));
    }

    /**
     * @param int $bytes
     */
    public function withMaxSpecSize(int $bytes): self
    {
        $this->assertPositiveInteger($bytes, 'Max spec size must be a positive integer');

        return $this->with(new BuilderConfig(maxSpecSizeBytes: $bytes));
    }

    /**
     * @param int $depth
     */
    public function withMaxSpecDepth(int $depth): self
    {
        $this->assertPositiveInteger($depth, 'Max spec depth must be a positive integer');

        return $this->with(new BuilderConfig(maxSpecDepth: $depth));
    }

    public function build(): OpenApiValidatorInterface
    {
        $document = $this->specLoader()->load();

        $this->externalRefDetector()->assertConfinement();

        return $this->assembleValidator($document);
    }

    private function with(BuilderConfig $overrides): self
    {
        return new self($this->config->merge($overrides));
    }

    private function resolveSpecPath(string $path): string
    {
        $realPath = realpath($path);
        if (false === $realPath) {
            throw new BuilderException(sprintf('Spec file does not exist: %s', $path));
        }

        return $realPath;
    }

    private function assertPositiveInteger(int $value, string $message): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($message);
        }
    }

    private function specLoader(): SpecLoader
    {
        return new SpecLoader($this->config, new CacheKeyBuilder($this->config));
    }

    private function externalRefDetector(): ExternalRefDetector
    {
        return new ExternalRefDetector($this->config);
    }

    private function assembleValidator(OpenApiDocument $document): OpenApiValidatorInterface
    {
        $maxRegexBacktracks = $this->config->maxRegexBacktracks ?? ValidatorConfiguration::DEFAULT_MAX_REGEX_BACKTRACKS;
        $pregExecutor = new PregExecutor($maxRegexBacktracks);
        $pool = $this->config->pool ?? new ValidatorPool();
        $userRegistry = $this->config->formatRegistry;
        $formatRegistry = null === $userRegistry
            ? BuiltinFormats::create($pregExecutor)
            : $userRegistry->withBase(BuiltinFormats::create($pregExecutor));
        $errorFormatter = $this->config->errorFormatter ?? new SimpleFormatter();
        $pathRegexCache = new PathRegexCache();
        $regexValidator = new RegexValidator(pregExecutor: $pregExecutor);
        $pathFinder = new PathFinder($document, $pathRegexCache, $pregExecutor);
        $logger = $this->config->logger ?? new NullLogger();
        $securityVerboseLogger = $this->config->securityVerboseLogger ?? new NullLogger();
        $fileResolver = new FileExternalRefResolver(
            allowedRoot: $this->config->externalRefAllowedRoot,
            maxBytes: $this->config->externalRefMaxBytes ?? FileExternalRefResolver::DEFAULT_MAX_REF_BYTES,
            logger: $securityVerboseLogger,
        );
        $refResolver = new RefResolver(builtinFileResolver: $fileResolver);

        $coercion = $this->config->coercion ?? false;
        $nullableAsType = $this->config->nullableAsType ?? true;
        $emptyArrayStrategy = $this->config->emptyArrayStrategy ?? EmptyArrayStrategy::AllowBoth;
        $securityValidation = $this->config->securityValidation ?? false;
        $strictFormats = $this->config->strictFormats ?? false;
        $reportDeprecated = $this->config->reportDeprecated ?? true;
        $maxJsonBodyBytes = $this->config->maxJsonBodyBytes ?? ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES;
        $maxMultipartBodyBytes = $this->config->maxMultipartBodyBytes ?? ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES;
        $maxStreamingRecords = $this->config->maxStreamingRecords ?? ValidatorConfiguration::DEFAULT_MAX_STREAMING_RECORDS;
        $strictStreaming = $this->config->strictStreaming ?? false;
        $strictCallbackRuntimeTemplate = $this->config->strictCallbackRuntimeTemplate ?? true;
        $strictCoercion = $this->config->strictCoercion ?? true;

        $context = ValidationStack::fromGroup(
            new ValidationGroup(
                root: new RootServices(
                    document: $document,
                    pool: $pool,
                    formatRegistry: $formatRegistry,
                    errorFormatter: $errorFormatter,
                    refResolver: $refResolver,
                    pathRegexCache: $pathRegexCache,
                    regexValidator: $regexValidator,
                    pregExecutor: $pregExecutor,
                ),
                options: new ValidatorOptions(
                    coercion: $coercion,
                    nullableAsType: $nullableAsType,
                    emptyArrayStrategy: $emptyArrayStrategy,
                    reportDeprecated: $reportDeprecated,
                    logger: $logger,
                    eventDispatcher: $this->config->eventDispatcher,
                    strictFormats: $strictFormats,
                    strictStreaming: $strictStreaming,
                    maxStreamingRecords: $maxStreamingRecords,
                    strictCoercion: $strictCoercion,
                    securityVerboseLogger: $securityVerboseLogger,
                ),
                bodyLimits: new BodyLimits(
                    maxJsonBodyBytes: $maxJsonBodyBytes,
                    maxMultipartBodyBytes: $maxMultipartBodyBytes,
                    maxRegexBacktracks: $maxRegexBacktracks,
                ),
            ),
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
                maxStreamingRecords: $maxStreamingRecords,
                strictCoercion: $strictCoercion,
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
                callbackValidation: new CallbackValidator($context, $securityValidation, $strictCallbackRuntimeTemplate),
                pathRegexCache: $pathRegexCache,
                regexValidator: $regexValidator,
                cache: $this->config->cache,
                eventDispatcher: $this->config->eventDispatcher,
            ),
        );
    }
}
