<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\DocumentParseException;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefTooLargeException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use InvalidArgumentException;

use function hash;
use function sort;
use function strlen;

final readonly class DocumentRepository
{
    private const string EMPTY_RETRIEVAL_URI_MESSAGE = 'Document retrieval URI must not be empty';

    private const string FINGERPRINT_PAIR_SEPARATOR = '|';

    private const string FINGERPRINT_ENTRY_SEPARATOR = "\n";

    /**
     * @param array<string, OpenApiDocument> $documents
     * @param array<string, string> $contents
     */
    private function __construct(private int $maxBytes, private array $documents, private array $contents) {}

    public static function empty(int $maxBytes = FileExternalRefResolver::DEFAULT_MAX_REF_BYTES): self
    {
        return new self($maxBytes, [], []);
    }

    public function withMaxBytes(int $maxBytes): self
    {
        return new self($maxBytes, $this->documents, $this->contents);
    }

    public function withDocument(string $retrievalUri, string $content, SchemaParserInterface $parser): self
    {
        if ('' === $retrievalUri) {
            throw new InvalidArgumentException(self::EMPTY_RETRIEVAL_URI_MESSAGE);
        }

        if (strlen($content) > $this->maxBytes) {
            throw new ExternalRefTooLargeException(max: $this->maxBytes);
        }

        return new self(
            $this->maxBytes,
            [...$this->documents, $retrievalUri => $this->parse($retrievalUri, $content, $parser)],
            [...$this->contents, $retrievalUri => $content],
        );
    }

    public function findByUri(string $uri): ?OpenApiDocument
    {
        return $this->documents[$uri] ?? null;
    }

    /** @return array<string, OpenApiDocument> retrieval URI mapped to parsed document */
    public function documents(): array
    {
        return $this->documents;
    }

    /**
     * @return array<string, string> retrieval URI mapped to raw registered content
     */
    public function contents(): array
    {
        return $this->contents;
    }

    public function assertWithinLimit(int $maxBytes): void
    {
        foreach ($this->contents as $content) {
            if (strlen($content) > $maxBytes) {
                throw new ExternalRefTooLargeException(max: $maxBytes);
            }
        }
    }

    public function fingerprint(): string
    {
        if ([] === $this->contents) {
            return '';
        }

        $entries = [];

        foreach ($this->contents as $retrievalUri => $content) {
            $entries[] = $retrievalUri . self::FINGERPRINT_PAIR_SEPARATOR . hash('sha256', $content);
        }

        sort($entries);

        return hash('sha256', implode(self::FINGERPRINT_ENTRY_SEPARATOR, $entries));
    }

    private function parse(string $retrievalUri, string $content, SchemaParserInterface $parser): OpenApiDocument
    {
        try {
            return $parser->parse($content);
        } catch (InvalidSchemaException|InvalidUtf8Exception|SpecTooLargeException $e) {
            throw new DocumentParseException($retrievalUri, previous: $e);
        }
    }
}
