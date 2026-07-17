<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WeakMap;

final class SchemaValidatorDependencies
{
    public readonly LoggerInterface $logger;
    public readonly ErrorFormatterInterface $errorFormatter;
    public readonly FormatRegistry $formatRegistry;
    public readonly BodyParser $bodyParser;

    /** @var WeakMap<OpenApiDocument, SchemaValidatorWithContext> */
    private WeakMap $rootSchemaValidators;

    public function __construct(
        public readonly ValidatorPool $pool,
        public readonly RefResolverInterface $refResolver,
        public readonly StatelessValidatorRegistry $statelessValidators,
        ?LoggerInterface $logger = null,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?ErrorFormatterInterface $errorFormatter = null,
        ?FormatRegistry $formatRegistry = null,
        ?BodyParser $bodyParser = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->errorFormatter = $errorFormatter ?? new SimpleFormatter();
        $this->formatRegistry = $formatRegistry ?? new FormatRegistry();
        $this->bodyParser = $bodyParser ?? new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(new QueryParser()),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        );
        /** @var WeakMap<OpenApiDocument, SchemaValidatorWithContext> $rootSchemaValidators */
        $rootSchemaValidators = new WeakMap();
        $this->rootSchemaValidators = $rootSchemaValidators;
    }

    /**
     * Shared top-level validator bound to a document. First-call-wins on
     * configuration: within one Validation\ValidatorDependencies both are constant.
     * Per-request state lives in Error\ValidationContext, passed by argument.
     */
    public function rootSchemaValidator(
        OpenApiDocument $document,
        ValidatorConfiguration $configuration,
    ): SchemaValidatorWithContext {
        if (isset($this->rootSchemaValidators[$document])) {
            /** @var SchemaValidatorWithContext $cached */
            $cached = $this->rootSchemaValidators[$document];

            return $cached;
        }

        $new = new SchemaValidatorWithContext($document, $this, $configuration);
        $this->rootSchemaValidators[$document] = $new;

        return $new;
    }
}
