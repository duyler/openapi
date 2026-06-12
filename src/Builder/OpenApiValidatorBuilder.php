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
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\PathFinder;
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
        private bool $strictFormats = false,
        private bool $reportDeprecated = true,
    ) {}

    public static function create(): self
    {
        return new self();
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

    public function withLogger(LoggerInterface $logger): self
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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $formatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

    public function withEmptyArrayStrategy(EmptyArrayStrategy $strategy): self
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
            emptyArrayStrategy: $strategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $dispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

    public function enableSecurityValidation(): self
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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: true,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
        );
    }

    public function enableStrictFormats(): self
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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: true,
            reportDeprecated: $this->reportDeprecated,
        );
    }

    public function enableReportDeprecated(): self
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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: true,
        );
    }

    public function build(): OpenApiValidatorInterface
    {
        $document = $this->loadSpec();

        $pool = $this->pool ?? new ValidatorPool();
        $formatRegistry = $this->formatRegistry ?? BuiltinFormats::create();
        $errorFormatter = $this->errorFormatter ?? SimpleFormatter::shared();
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
            emptyArrayStrategy: $this->emptyArrayStrategy,
            eventDispatcher: $this->eventDispatcher,
            pathFinder: $pathFinder,
            securityValidation: $this->securityValidation,
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
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
            return self::CACHE_KEY_FILE_PREFIX . md5($path);
        }

        return self::CACHE_KEY_FILE_PREFIX . md5($realPath);
    }

    private function generateCacheKeyFromString(string $content): string
    {
        return self::CACHE_KEY_CONTENT_PREFIX . md5($content);
    }
}
