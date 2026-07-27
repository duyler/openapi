<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation\Internal;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Validation\Internal\BodyLimits;
use Duyler\OpenApi\Validator\Validation\Internal\RootServices;
use Duyler\OpenApi\Validator\Validation\Internal\ValidatorDependenciesGroup;
use Duyler\OpenApi\Validator\Validation\Internal\ValidatorOptions;
use Duyler\OpenApi\Validator\Validation\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ValidatorDependenciesGroup::class)]
#[CoversClass(RootServices::class)]
#[CoversClass(ValidatorOptions::class)]
#[CoversClass(BodyLimits::class)]
#[CoversClass(ValidatorDependencies::class)]
final class ValidatorDependenciesGroupTest extends TestCase
{
    private const string SIMPLE_YAML = <<<YAML
openapi: 3.0.3
info:
  title: T
  version: 1.0.0
paths:
  /ping:
    get:
      responses:
        '200':
          description: ok
YAML;

    #[Test]
    public function from_group_builds_dependencies_with_defaults(): void
    {
        $document = $this->buildDocument();

        $group = new ValidatorDependenciesGroup(
            root: new RootServices(
                document: $document,
                pool: new ValidatorPool(),
                formatRegistry: new FormatRegistry(),
                errorFormatter: new SimpleFormatter(),
                refResolver: new RefResolver(),
            ),
            options: new ValidatorOptions(),
        );

        $deps = ValidatorDependencies::fromGroup($group);

        self::assertSame($document, $deps->document);
        self::assertSame($group->root->pool, $deps->pool);
        self::assertSame($group->root->formatRegistry, $deps->formatRegistry);
        self::assertSame($group->root->errorFormatter, $deps->errorFormatter);
        self::assertSame($group->root->refResolver, $deps->refResolver);
        // Defaults from ValidatorOptions
        self::assertFalse($deps->coercion);
        self::assertTrue($deps->nullableAsType);
        self::assertSame(EmptyArrayStrategy::AllowBoth, $deps->emptyArrayStrategy);
        self::assertFalse($deps->reportDeprecated);
        self::assertFalse($deps->strictFormats);
        self::assertTrue($deps->strictCoercion);
        // Defaults from BodyLimits
        self::assertSame(ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES, $deps->maxJsonBodyBytes);
        self::assertSame(ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES, $deps->maxMultipartBodyBytes);
        self::assertSame(PregExecutor::DEFAULT_MAX_BACKTRACKS, $deps->maxRegexBacktracks);
        // Wired sub-services
        self::assertNotNull($deps->requestValidator);
        self::assertNotNull($deps->responseValidator);
        self::assertNotNull($deps->schemaValidatorWithContext);
    }

    #[Test]
    public function from_group_propagates_options_and_limits(): void
    {
        $document = $this->buildDocument();

        $group = new ValidatorDependenciesGroup(
            root: new RootServices(
                document: $document,
                pool: new ValidatorPool(),
                formatRegistry: new FormatRegistry(),
                errorFormatter: new SimpleFormatter(),
                refResolver: new RefResolver(),
            ),
            options: new ValidatorOptions(
                coercion: true,
                nullableAsType: false,
                emptyArrayStrategy: EmptyArrayStrategy::Reject,
                reportDeprecated: true,
                logger: new NullLogger(),
                strictFormats: true,
                strictStreaming: true,
                strictCoercion: false,
            ),
            bodyLimits: new BodyLimits(
                maxJsonBodyBytes: 1024,
                maxMultipartBodyBytes: 2048,
                maxRegexBacktracks: 999,
            ),
        );

        $deps = ValidatorDependencies::fromGroup($group);

        self::assertTrue($deps->coercion);
        self::assertFalse($deps->nullableAsType);
        self::assertSame(EmptyArrayStrategy::Reject, $deps->emptyArrayStrategy);
        self::assertTrue($deps->reportDeprecated);
        self::assertTrue($deps->strictFormats);
        self::assertTrue($deps->strictStreaming);
        self::assertFalse($deps->strictCoercion);
        self::assertSame(1024, $deps->maxJsonBodyBytes);
        self::assertSame(2048, $deps->maxMultipartBodyBytes);
        self::assertSame(999, $deps->maxRegexBacktracks);
    }

    #[Test]
    public function validator_options_to_configuration_round_trip(): void
    {
        $options = new ValidatorOptions(
            coercion: true,
            nullableAsType: false,
            emptyArrayStrategy: EmptyArrayStrategy::PreferArray,
            reportDeprecated: true,
            strictFormats: true,
            strictStreaming: true,
            strictCoercion: false,
        );

        $config = $options->toValidatorConfiguration();

        self::assertTrue($config->coercion);
        self::assertFalse($config->nullableAsType);
        self::assertSame(EmptyArrayStrategy::PreferArray, $config->emptyArrayStrategy);
        self::assertTrue($config->reportDeprecated);
        self::assertTrue($config->strictFormats);
        self::assertTrue($config->strictStreaming);
        self::assertFalse($config->strictCoercion);
        // BodyLimits default through ValidatorConfiguration::DEFAULT_* constants
        self::assertSame(ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES, $config->maxJsonBodyBytes);
        self::assertSame(ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES, $config->maxMultipartBodyBytes);
    }

    #[Test]
    public function root_services_defaults_propagate(): void
    {
        $document = $this->buildDocument();

        $root = new RootServices(
            document: $document,
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            errorFormatter: new SimpleFormatter(),
            refResolver: new RefResolver(),
        );

        // Defaults are wired
        self::assertInstanceOf(PathRegexCache::class, $root->pathRegexCache);
        self::assertInstanceOf(RegexValidator::class, $root->regexValidator);
        self::assertInstanceOf(PregExecutor::class, $root->pregExecutor);
    }

    private function buildDocument(): OpenApiDocument
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build()
            ->getDocument();
    }
}
