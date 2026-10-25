<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\JsonSchemaSuite;

use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Test\Support\JsonSchemaSuiteLocator;
use Duyler\OpenApi\Test\Support\SuiteRemoteDocuments;
use Duyler\OpenApi\Test\Support\SuiteValidatorFactory;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_starts_with;

final class SuiteRemotesSmokeTest extends TestCase
{
    #[Test]
    public function all_remote_documents_parse_through_envelope(): void
    {
        $parser = new JsonParser();
        $documents = SuiteRemoteDocuments::all();

        $this->assertNotEmpty($documents);

        foreach ($documents as $retrievalUri => $content) {
            $document = $parser->parse($content);

            self::assertNotNull($document->self, sprintf('Envelope $self must be set for %s', $retrievalUri));

            if (str_starts_with($retrievalUri, 'https://json-schema.org/')) {
                self::assertSame(
                    $retrievalUri,
                    (string) $document->self,
                    sprintf('Metaschema envelope $self must equal the canonical URI for %s', $retrievalUri),
                );

                continue;
            }

            self::assertStringStartsWith(
                JsonSchemaSuiteLocator::REMOTE_BASE_URI,
                (string) $document->self,
                sprintf('Envelope $self must be a suite URI for %s', $retrievalUri),
            );
        }
    }

    #[Test]
    public function metaschema_documents_are_registered_under_canonical_uris(): void
    {
        $documents = SuiteRemoteDocuments::all();

        foreach ([
            'https://json-schema.org/draft/2020-12/schema',
            'https://json-schema.org/draft/2020-12/meta/core',
            'https://json-schema.org/draft/2020-12/meta/applicator',
            'https://json-schema.org/draft/2020-12/meta/unevaluated',
            'https://json-schema.org/draft/2020-12/meta/validation',
            'https://json-schema.org/draft/2020-12/meta/meta-data',
            'https://json-schema.org/draft/2020-12/meta/format-annotation',
            'https://json-schema.org/draft/2020-12/meta/content',
        ] as $canonicalUri) {
            self::assertArrayHasKey($canonicalUri, $documents, sprintf('Missing metaschema document: %s', $canonicalUri));
        }
    }

    #[Test]
    public function remote_schema_from_suite_case_resolves_via_with_document(): void
    {
        $validator = SuiteValidatorFactory::forSchema([
            '$ref' => 'http://localhost:1234/draft2020-12/integer.json#/components/schemas/Subject',
        ]);

        $validator->validateSchema(42, SuiteValidatorFactory::subjectSchemaRef());

        $this->expectException(ValidationException::class);
        $validator->validateSchema('not-an-integer', SuiteValidatorFactory::subjectSchemaRef());
    }

    #[Test]
    public function remote_schema_without_fragment_resolves_via_identified_root(): void
    {
        $validator = SuiteValidatorFactory::forSchema([
            '$ref' => 'http://localhost:1234/draft2020-12/tree.json',
        ]);

        $validator->validateSchema(['data' => 1], SuiteValidatorFactory::subjectSchemaRef());

        $this->expectException(ValidationException::class);
        $validator->validateSchema('not-a-tree-object', SuiteValidatorFactory::subjectSchemaRef());
    }

    #[Test]
    public function remotes_are_registered_for_suite_validator(): void
    {
        $this->assertGreaterThan(0, SuiteValidatorFactory::remotesCount());
    }
}
