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
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\SchemaValidatorAdapter;
use Duyler\OpenApi\Validator\Validation\ValidatorDependencies as ValidationAssembler;
use Duyler\OpenApi\Validator\Validation\WebhookValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Exception;
use InvalidArgumentException;
use JsonException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Response\Exception\TooManyRecordsException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function is_array;
use function mb_check_encoding;
use function sprintf;
use function str_starts_with;
use function strlen;
use function is_string;

use function array_key_exists;

use const JSON_THROW_ON_ERROR;

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

    /**
     * Enable verbose PSR-3 logging of caller-sensitive security and
     * external-ref diagnostics (scheme names, parameter locations,
     * resolved filesystem paths). Without this call, the security
     * validator and the builtin FileExternalRefResolver use a
     * NullLogger, so the only signal an unauthenticated caller can
     * observe is a generic exception message. Pass a logger whose
     * audience is trusted (operators / a sealed log pipeline) to
     * surface the underlying details at debug level.
     */
    public function withSecurityVerboseLogging(LoggerInterface $logger): self
    {
        return $this->with(new BuilderConfig(securityVerboseLogger: $logger));
    }

    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return $this->with(new BuilderConfig(errorFormatter: $formatter));
    }

    /**
     * Configure the validator to use {@see DetailedFormatter}, optionally with
     * sensitive values (such as the raw input that failed format validation)
     * included in the rendered error output.
     *
     * Default is `false`: raw invalid inputs are omitted from formatter output
     * to prevent accidental disclosure of credentials sent against
     * `format: password`, `format: uuid`, etc. (CWE-532). Pass `true` only in
     * trusted debugging contexts.
     *
     * When chained with {@see withErrorFormatter()}, the call order wins: a
     * subsequent `withErrorFormatter()` overrides the formatter installed by
     * this method, and vice versa.
     */
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
        $registry = ($this->config->formatRegistry ?? BuiltinFormats::create())
            ->registerFormat($type, $format, $validator);

        return $this->with(new BuilderConfig(formatRegistry: $registry));
    }

    public function enableCoercion(): self
    {
        return $this->with(new BuilderConfig(coercion: true));
    }

    /**
     * Disable strict coercion mode and fall back to the legacy non-strict
     * coercion behaviour.
     *
     * Strict coercion is the default since SEC-13/SEC-14/SEC-15: it rejects
     * arbitrary strings coerced to boolean (e.g. "admin" no longer silently
     * becomes true), throws on float-to-integer overflow outside the int64
     * range, and rejects numeric strings that lose precision when cast to
     * float. Call this method only when the application explicitly relies on
     * the legacy lax coercion semantics.
     */
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

    /**
     * Override the directory that external file:// $ref references must
     * remain inside. The default is auto-derived from the spec file
     * directory (dirname of the realpath of the path passed to
     * fromYamlFile / fromJsonFile), which keeps external refs inside the
     * spec directory. Use this method to widen or narrow that root, for
     * example to allow references into a shared sibling components
     * directory. Passing a path that does not exist throws BuilderException
     * at call time so the failure surfaces before build().
     *
     * Call this method AFTER fromYamlFile / fromJsonFile: the auto-derive
     * step unconditionally sets the value, so a withExternalRefAllowedRoot
     * call placed before fromYamlFile / fromJsonFile will be silently
     * overwritten.
     */
    public function withExternalRefAllowedRoot(string $path): self
    {
        $realPath = realpath($path);
        if (false === $realPath) {
            throw new BuilderException(sprintf('External ref allowed root does not exist: %s', $path));
        }

        return $this->with(new BuilderConfig(externalRefAllowedRoot: $realPath));
    }

    /**
     * Enable security scheme validation for requests
     *
     * When enabled, the validator checks that required security credentials
     * are present in the request headers, query parameters, or cookies.
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
     */
    public function enableReportDeprecated(): self
    {
        return $this->with(new BuilderConfig(reportDeprecated: true));
    }

    /**
     * Override the maximum allowed size, in bytes, for non-multipart request
     * and response bodies (JSON, XML, text). Bodies larger than this cap are
     * rejected before being fully materialised in memory.
     */
    public function withMaxJsonBodySize(int $maxBytes): self
    {
        return $this->with(new BuilderConfig(maxJsonBodyBytes: $maxBytes));
    }

    /**
     * Override the maximum allowed size, in bytes, for multipart request and
     * response bodies. multipart payloads typically carry larger uploads, so
     * the cap is kept independent from the JSON cap.
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
     */
    public function withMaxRegexBacktracks(int $maxBacktracks): self
    {
        return $this->with(new BuilderConfig(maxRegexBacktracks: $maxBacktracks));
    }

    /**
     * Enable strict callback runtime template resolution.
     *
     * @deprecated since 1.x, will be removed in 2.0. Strict mode is now the
     *             default: callback expressions that use runtime templates
     *             such as `{$request.body#/callback_url}` throw an
     *             {@see UnresolvableCallbackPathException} by default instead
     *             of being treated as wildcards that accept any URL. This
     *             method is retained as a no-op for backward compatibility
     *             with callers that explicitly opted in. To restore the
     *             legacy wildcard behaviour, use
     *             {@see disableStrictCallbackRuntimeTemplate()}.
     */
    public function enableStrictCallbackRuntimeTemplate(): self
    {
        return $this;
    }

    /**
     * Disable strict callback runtime template resolution.
     *
     * SECURITY WARNING: When disabled, callback expressions that use runtime
     * templates such as `{$request.body#/callback_url}` are treated as
     * wildcards that accept any URL, and declared security checks on the
     * callback pathItem still pass against an attacker-controlled URL. If
     * the resolved URL is then used by the application for an outbound HTTP
     * request, this enables SSRF via the callback parameter.
     *
     * Only use this method when the application validates callback URLs
     * through another mechanism (allowlist, signed URLs, application-level
     * allowlist of outbound destinations). The strict default is the
     * fail-closed posture recommended for new code.
     */
    public function disableStrictCallbackRuntimeTemplate(): self
    {
        return $this->with(new BuilderConfig(strictCallbackRuntimeTemplate: false));
    }

    /**
     * Override the maximum allowed size, in bytes, for external file:// $ref
     * payloads loaded by the builtin FileExternalRefResolver. Files larger
     * than this cap are rejected before being fully materialised in memory,
     * defending against DoS via attacker-controlled large files or special
     * files like /dev/zero. The default is 10 MB
     * (FileExternalRefResolver::DEFAULT_MAX_REF_BYTES).
     *
     * @param int $bytes Positive integer size in bytes; values <= 0 raise
     *                   InvalidArgumentException because they would either
     *                   reject every file (0) or compare nonsensically
     *                   against read counts (negative).
     */
    public function withExternalRefMaxBytes(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException(
                'External ref max bytes must be a positive integer',
            );
        }

        return $this->with(new BuilderConfig(externalRefMaxBytes: $bytes));
    }

    /**
     * Override the maximum number of records the streaming content parser
     * (NDJSON, SSE, JSON Text Sequences) will accept from a single response
     * before raising {@see TooManyRecordsException}.
     *
     * The default is 100_000 records, sufficient for typical streaming APIs
     * while bounding the memory impact of validating an attacker-controlled
     * response composed of a very large number of small records.
     *
     * @param int $max Positive integer record cap; values <= 0 raise
     *                 InvalidArgumentException because they would either
     *                 reject every record (0) or compare nonsensically
     *                 against an accumulated count (negative).
     */
    public function withMaxStreamingRecords(int $max): self
    {
        if ($max <= 0) {
            throw new InvalidArgumentException(
                'Max streaming records must be a positive integer',
            );
        }

        return $this->with(new BuilderConfig(maxStreamingRecords: $max));
    }

    /**
     * Override the maximum allowed size, in bytes, for an OpenAPI spec
     * payload parsed by YamlParser. Specs larger than this cap are rejected
     * before YAML parsing begins, defending against OOM on
     * attacker-controlled or accidentally oversized specs. The default is
     * 1 MB (YamlParser::DEFAULT_MAX_SPEC_BYTES) and is sized to accept
     * typical OpenAPI documents including the bundled petstore spec.
     *
     * @param int $bytes Positive integer size in bytes; values <= 0 raise
     *                   InvalidArgumentException because they would either
     *                   reject every spec (0) or compare nonsensically
     *                   against content length (negative).
     */
    public function withMaxSpecSize(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException(
                'Max spec size must be a positive integer',
            );
        }

        return $this->with(new BuilderConfig(maxSpecSizeBytes: $bytes));
    }

    /**
     * Override the maximum allowed nesting depth for an OpenAPI spec
     * payload parsed by `YamlParser` or `JsonParser`. Specs whose
     * nesting exceeds this cap are rejected after parsing, defending
     * against billion-laughs-style YAML or deeply-nested JSON that can
     * cause stack overflow or OOM (closes SEC-18 for YAML, extends the
     * same protection to JSON). The default is 100
     * (`YamlParser::DEFAULT_MAX_SPEC_DEPTH`) and is sized to accept
     * typical OpenAPI documents including the bundled petstore spec.
     *
     * @param int $depth Positive integer depth cap; values <= 0 raise
     *                   InvalidArgumentException because they would either
     *                   reject every spec (0) or compare nonsensically
     *                   against depth (negative).
     */
    public function withMaxSpecDepth(int $depth): self
    {
        if ($depth <= 0) {
            throw new InvalidArgumentException(
                'Max spec depth must be a positive integer',
            );
        }

        return $this->with(new BuilderConfig(maxSpecDepth: $depth));
    }

    /**
     * Build the validator from the configured spec. The internal call
     * order is significant: `loadSpec()` runs first to surface the
     * canonical parse error via `parseSpec()`, then
     * `assertExternalRefConfinement()` runs to fail-closed on
     * string-loaded specs that contain an external `$ref`. The guard
     * silently passes on a parse failure because `loadSpec()` has
     * already thrown — do not reorder these calls.
     */
    public function build(): OpenApiValidatorInterface
    {
        $document = $this->loadSpec();

        $this->assertExternalRefConfinement();

        $maxRegexBacktracks = $this->config->maxRegexBacktracks ?? ValidatorConfiguration::DEFAULT_MAX_REGEX_BACKTRACKS;
        $pregExecutor = new PregExecutor($maxRegexBacktracks);
        $pool = $this->config->pool ?? new ValidatorPool();
        $formatRegistry = $this->config->formatRegistry ?? BuiltinFormats::create($pregExecutor);
        $errorFormatter = $this->config->errorFormatter ?? new SimpleFormatter();
        $pathRegexCache = new PathRegexCache();
        $regexValidator = new RegexValidator();
        $pathFinder = new PathFinder($document, $pathRegexCache);
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
        $strictStreaming = $this->config->strictStreaming ?? false;
        $strictCallbackRuntimeTemplate = $this->config->strictCallbackRuntimeTemplate ?? true;
        $strictCoercion = $this->config->strictCoercion ?? true;

        $context = new ValidationAssembler(
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
            securityVerboseLogger: $securityVerboseLogger,
            strictCoercion: $strictCoercion,
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

        if (false === is_file($this->config->specPath)) {
            throw new BuilderException(sprintf('Spec file does not exist: %s', $this->config->specPath));
        }

        $content = file_get_contents($this->config->specPath);

        if (false === $content) {
            throw new BuilderException(sprintf('Failed to read spec file: %s', $this->config->specPath));
        }

        $cacheKey = $this->generateCacheKeyFromFile($this->config->specPath, $content);

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
                $parser = new YamlParser(
                    $deprecationLogger,
                    $this->config->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES,
                    $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH,
                );

                return $parser->parse($content);
            }

            if ('json' === $this->config->specType) {
                $parser = new JsonParser(
                    $deprecationLogger,
                    $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH,
                );

                return $parser->parse($content);
            }

            throw new BuilderException(sprintf('Unsupported spec type: %s', $this->config->specType ?? 'none'));
        } catch (InvalidUtf8Exception|SpecTooLargeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new BuilderException(
                sprintf('Failed to parse spec: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * Fail-closed guard for specs loaded via `fromYamlString()` /
     * `fromJsonString()`: when the parsed spec contains an external
     * `$ref` (any ref that does not start with `#/`) and the caller has
     * not opted into path-confinement via `withExternalRefAllowedRoot()`,
     * the build aborts with a `BuilderException`. File-loaded specs and
     * specs that explicitly set an allowed root are not affected — the
     * builtin `FileExternalRefResolver` already confines resolution for
     * them. Specs that contain only internal JSON pointer refs (`#/...`)
     * pass through unchanged for backward compatibility.
     */
    private function assertExternalRefConfinement(): void
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
        $externalRef = $this->detectExternalRefs($parsedSpec, 0, $maxDepth);

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
     * Walk the parsed spec tree and return the first external `$ref`
     * value found. An external `$ref` is any string value keyed by
     * `$ref` that does not start with `#/` (the JSON pointer prefix
     * marking an in-document reference). Discriminator mapping and
     * defaultMapping values are also treated as external refs because
     * `DiscriminatorValidator` resolves them through `RefResolver`
     * exactly like a `$ref`. The recursion is bounded by
     * `max(maxSpecDepth, ValidationContext::MAX_DEPTH)` as a second
     * line of defense: the canonical parsers (`YamlParser`,
     * `JsonParser`) already reject specs deeper than `maxSpecDepth`
     * at parse time, but this bound ensures the walker never
     * stack-overflows even if a parser is misconfigured or bypassed.
     * Returns null when no external ref is present.
     *
     * The $maxDepth bound is computed once by the caller and threaded
     * through the recursion, because BuilderConfig is readonly and the
     * bound is therefore constant for the entire walk — recomputing it
     * per node is wasted work on deeply nested specs.
     *
     * @param array<array-key, mixed> $data
     */
    private function detectExternalRefs(array $data, int $depth, int $maxDepth): ?string
    {
        if ($depth > $maxDepth) {
            return null;
        }

        if (array_key_exists('$ref', $data)) {
            /** @var mixed $ref */
            $ref = $data['$ref'];

            if (is_string($ref) && !str_starts_with($ref, '#/')) {
                return $ref;
            }
        }

        if (array_key_exists('discriminator', $data)) {
            /** @var mixed $discriminatorRaw */
            $discriminatorRaw = $data['discriminator'];

            if (is_array($discriminatorRaw)) {
                /** @var array<array-key, mixed> $discriminator */
                $discriminator = $discriminatorRaw;

                if (array_key_exists('defaultMapping', $discriminator)) {
                    /** @var mixed $defaultMapping */
                    $defaultMapping = $discriminator['defaultMapping'];

                    if (is_string($defaultMapping) && !str_starts_with($defaultMapping, '#/')) {
                        return $defaultMapping;
                    }
                }

                if (array_key_exists('mapping', $discriminator)) {
                    /** @var mixed $mappingRaw */
                    $mappingRaw = $discriminator['mapping'];

                    if (is_array($mappingRaw)) {
                        /** @var array<array-key, mixed> $mapping */
                        $mapping = $mappingRaw;

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
                }
            }
        }

        foreach ($data as $value) {
            /** @var mixed $value */
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->detectExternalRefs($value, $depth + 1, $maxDepth);
            if (null !== $nested) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Best-effort parse of the raw spec content into a PHP array. Used
     * only by {@see assertExternalRefConfinement()} to walk the spec
     * tree before the `OpenApiValidator` is assembled. Bounded by the
     * same byte-size cap as `YamlParser` for YAML content and the same
     * UTF-8 check as `JsonParser` for JSON content. The nesting-depth
     * cap is enforced inside {@see detectExternalRefs()} rather than
     * here, because that is where unbounded recursion would otherwise
     * occur. Any parse failure returns an empty array — `loadSpec()`
     * already surfaces the canonical parse error via `parseSpec()`,
     * and the walker must not shadow that exception.
     *
     * @return array<array-key, mixed>
     */
    private function parseSpecContentAsArray(string $content): array
    {
        if ('json' === $this->config->specType) {
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

    /**
     * Compute the SchemaCache key for a file-loaded spec.
     *
     * The key incorporates the realpath AND a SHA-256 hash of the file
     * contents. This prevents cache-poisoning via size-preserving or
     * mtime-preserving spec tampering (OWASP ASVS V8.1.3, CWE-349,
     * CWE-1023). mtime and size are intentionally NOT part of the key:
     * they offered no protection once an attacker controls write-access
     * to the spec file.
     *
     * When realpath() returns false (file vanished mid-call), the key
     * degrades to a hash of the unresolved $path argument plus the
     * content hash. This preserves uniqueness across caller-distinct
     * paths even when realpath fails.
     */
    private function generateCacheKeyFromFile(string $path, string $content): string
    {
        $realPath = realpath($path);
        $pathComponent = false === $realPath ? $path : $realPath;
        $contentHash = hash('sha256', $content);

        return self::CACHE_KEY_FILE_PREFIX . hash('sha256', $pathComponent . '|' . $contentHash);
    }

    private function generateCacheKeyFromString(string $content): string
    {
        return self::CACHE_KEY_CONTENT_PREFIX . hash('sha256', $content);
    }
}
