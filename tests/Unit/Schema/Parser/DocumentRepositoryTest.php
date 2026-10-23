<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\DocumentParseException;
use Duyler\OpenApi\Schema\Parser\DocumentRepository;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefTooLargeException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class DocumentRepositoryTest extends TestCase
{
    private const string RETRIEVAL_URI = 'https://api.example.com/v1.json';

    #[Test]
    public function empty_repository_has_no_documents(): void
    {
        $repository = DocumentRepository::empty();

        $this->assertNull($repository->findByUri(self::RETRIEVAL_URI));
    }

    #[Test]
    public function with_document_parses_content_immediately_and_stores_it(): void
    {
        $repository = DocumentRepository::empty()->withDocument(
            self::RETRIEVAL_URI,
            $this->documentJson(['openapi' => '3.1.0']),
            new JsonParser(),
        );

        $document = $repository->findByUri(self::RETRIEVAL_URI);

        $this->assertNotNull($document);
        $this->assertSame('3.1.0', $document->openapi);
    }

    #[Test]
    public function with_document_accumulates_documents_and_keeps_previous_ones(): void
    {
        $firstUri = 'https://api.example.com/first.json';
        $secondUri = 'https://api.example.com/second.json';

        $repository = DocumentRepository::empty()
            ->withDocument($firstUri, $this->documentJson(['title' => 'First']), new JsonParser())
            ->withDocument($secondUri, $this->documentJson(['title' => 'Second']), new JsonParser());

        $this->assertSame('First', $repository->findByUri($firstUri)?->info->title);
        $this->assertSame('Second', $repository->findByUri($secondUri)?->info->title);
    }

    #[Test]
    public function with_document_same_uri_replaces_previous_document(): void
    {
        $repository = DocumentRepository::empty()
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson(['title' => 'First']), new JsonParser())
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson(['title' => 'Second']), new JsonParser());

        $this->assertSame('Second', $repository->findByUri(self::RETRIEVAL_URI)?->info->title);
    }

    #[Test]
    public function with_document_invalid_content_throws_document_parse_exception_with_uri(): void
    {
        $repository = DocumentRepository::empty();

        try {
            $repository->withDocument(
                self::RETRIEVAL_URI,
                '{"openapi": "9.9.9", "info": {}, "MARKER_SECRET_CONTENT": true}',
                new JsonParser(),
            );
            $this->fail('Expected DocumentParseException to be thrown');
        } catch (DocumentParseException $e) {
            $this->assertStringContainsString(self::RETRIEVAL_URI, $e->getMessage());
            $this->assertSame(self::RETRIEVAL_URI, $e->retrievalUri);
            $this->assertStringNotContainsString('MARKER_SECRET_CONTENT', $e->getMessage());
        }
    }

    #[Test]
    public function with_document_malformed_json_throws_document_parse_exception(): void
    {
        $repository = DocumentRepository::empty();

        $this->expectException(DocumentParseException::class);
        $repository->withDocument(self::RETRIEVAL_URI, '{not json', new JsonParser());
    }

    #[Test]
    public function with_document_content_over_max_bytes_throws_external_ref_too_large(): void
    {
        $repository = DocumentRepository::empty(maxBytes: 64);

        try {
            $repository->withDocument(
                self::RETRIEVAL_URI,
                $this->documentJson(['title' => str_repeat('a', 256)]),
                new JsonParser(),
            );
            $this->fail('Expected ExternalRefTooLargeException to be thrown');
        } catch (ExternalRefTooLargeException $e) {
            $this->assertSame(64, $e->max);
        }
    }

    #[Test]
    public function with_document_empty_retrieval_uri_throws_invalid_argument(): void
    {
        $repository = DocumentRepository::empty();

        $this->expectException(InvalidArgumentException::class);
        $repository->withDocument('', $this->documentJson([]), new JsonParser());
    }

    #[Test]
    public function with_document_repository_is_immutable(): void
    {
        $base = DocumentRepository::empty();

        $next = $base->withDocument(self::RETRIEVAL_URI, $this->documentJson([]), new JsonParser());

        $this->assertNull($base->findByUri(self::RETRIEVAL_URI));
        $this->assertNotNull($next->findByUri(self::RETRIEVAL_URI));
    }

    #[Test]
    public function contents_returns_raw_content_by_retrieval_uri(): void
    {
        $content = $this->documentJson(['title' => 'Raw']);

        $repository = DocumentRepository::empty()->withDocument(self::RETRIEVAL_URI, $content, new JsonParser());

        $this->assertSame([self::RETRIEVAL_URI => $content], $repository->contents());
    }

    #[Test]
    public function fingerprint_is_empty_for_empty_repository(): void
    {
        $this->assertSame('', DocumentRepository::empty()->fingerprint());
    }

    #[Test]
    public function fingerprint_is_independent_of_registration_order(): void
    {
        $firstUri = 'https://api.example.com/first.json';
        $secondUri = 'https://api.example.com/second.json';

        $forward = DocumentRepository::empty()
            ->withDocument($firstUri, $this->documentJson(['title' => 'First']), new JsonParser())
            ->withDocument($secondUri, $this->documentJson(['title' => 'Second']), new JsonParser());

        $backward = DocumentRepository::empty()
            ->withDocument($secondUri, $this->documentJson(['title' => 'Second']), new JsonParser())
            ->withDocument($firstUri, $this->documentJson(['title' => 'First']), new JsonParser());

        $this->assertNotSame('', $forward->fingerprint());
        $this->assertSame($forward->fingerprint(), $backward->fingerprint());
    }

    #[Test]
    public function fingerprint_changes_when_content_changes(): void
    {
        $original = DocumentRepository::empty()
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson(['title' => 'A']), new JsonParser());

        $changed = DocumentRepository::empty()
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson(['title' => 'B']), new JsonParser());

        $this->assertNotSame($original->fingerprint(), $changed->fingerprint());
    }

    #[Test]
    public function assert_within_limit_passes_when_all_documents_fit(): void
    {
        $repository = DocumentRepository::empty()
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson([]), new JsonParser());

        $repository->assertWithinLimit(FileExternalRefResolver::DEFAULT_MAX_REF_BYTES);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_within_limit_throws_when_document_exceeds_final_limit(): void
    {
        $repository = DocumentRepository::empty()
            ->withDocument(self::RETRIEVAL_URI, $this->documentJson([]), new JsonParser());

        try {
            $repository->assertWithinLimit(16);
            $this->fail('Expected ExternalRefTooLargeException to be thrown');
        } catch (ExternalRefTooLargeException $e) {
            $this->assertSame(16, $e->max);
        }
    }

    #[Test]
    public function with_max_bytes_applies_new_limit_to_next_registration(): void
    {
        $repository = DocumentRepository::empty(2048)
            ->withDocument('https://api.example.com/first.json', $this->documentJson([]), new JsonParser())
            ->withMaxBytes(64);

        $this->expectException(ExternalRefTooLargeException::class);

        $repository->withDocument('https://api.example.com/second.json', $this->documentJson([]), new JsonParser());
    }

    /**
     * @param array<array-key, mixed> $info
     */
    private function documentJson(array $info): string
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => $info + ['version' => '1.0.0', 'title' => 'registered document'],
            'paths' => [],
        ];

        return json_encode($document, JSON_THROW_ON_ERROR);
    }
}
