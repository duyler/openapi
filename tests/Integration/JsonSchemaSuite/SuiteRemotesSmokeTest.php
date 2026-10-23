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
            self::assertStringStartsWith(
                JsonSchemaSuiteLocator::REMOTE_BASE_URI,
                (string) $document->self,
                sprintf('Envelope $self must be a suite URI for %s', $retrievalUri),
            );
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
