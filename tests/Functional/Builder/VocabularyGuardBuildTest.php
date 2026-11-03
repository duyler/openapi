<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\LegacyKeywordWarningEvent;
use Duyler\OpenApi\Event\VocabularyDisabledWarningEvent;
use Duyler\OpenApi\Schema\Exception\VocabularyNotSupportedException;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class VocabularyGuardBuildTest extends TestCase
{
    private const string UNKNOWN_VOCABULARY_URI = 'https://example.com/vocab/custom';

    public static function provideSupportedVocabularyUris(): Generator
    {
        foreach ([
            'https://json-schema.org/draft/2020-12/vocab/core',
            'https://json-schema.org/draft/2020-12/vocab/applicator',
            'https://json-schema.org/draft/2020-12/vocab/unevaluated',
            'https://json-schema.org/draft/2020-12/vocab/validation',
            'https://json-schema.org/draft/2020-12/vocab/meta-data',
            'https://json-schema.org/draft/2020-12/vocab/format-annotation',
            'https://json-schema.org/draft/2020-12/vocab/format-assertion',
            'https://json-schema.org/draft/2020-12/vocab/content',
            'https://spec.openapis.org/oas/3.1/vocab/base',
        ] as $uri) {
            yield $uri => [$uri];
        }
    }

    #[Test]
    #[DataProvider('provideSupportedVocabularyUris')]
    public function build_passes_with_supported_required_vocabulary(string $uri): void
    {
        $vocabularyWarnings = [];
        $legacyWarnings = [];
        $dispatcher = self::createWarningDispatcher($vocabularyWarnings, $legacyWarnings);

        $validator = $this->buildVocabularySpec($uri, 'true', $dispatcher);

        self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);
        self::assertSame([], $vocabularyWarnings);
        self::assertSame([], $legacyWarnings);
    }

    #[Test]
    public function build_refuses_unsupported_required_vocabulary(): void
    {
        $this->expectException(VocabularyNotSupportedException::class);
        $this->expectExceptionMessage(self::UNKNOWN_VOCABULARY_URI);

        $this->buildVocabularySpec(self::UNKNOWN_VOCABULARY_URI, 'true');
    }

    #[Test]
    public function build_accepts_unsupported_optional_vocabulary_with_warning(): void
    {
        $vocabularyWarnings = [];
        $legacyWarnings = [];
        $dispatcher = self::createWarningDispatcher($vocabularyWarnings, $legacyWarnings);

        $validator = $this->buildVocabularySpec(self::UNKNOWN_VOCABULARY_URI, 'false', $dispatcher);

        self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);
        self::assertCount(1, $vocabularyWarnings);
        self::assertInstanceOf(VocabularyDisabledWarningEvent::class, $vocabularyWarnings[0]);
        self::assertSame(self::UNKNOWN_VOCABULARY_URI, $vocabularyWarnings[0]->vocabularyUri);
        self::assertSame([], $legacyWarnings);
    }

    #[Test]
    public function build_warns_on_legacy_recursive_ref_and_preserves_extras(): void
    {
        $vocabularyWarnings = [];
        $legacyWarnings = [];
        $dispatcher = self::createWarningDispatcher($vocabularyWarnings, $legacyWarnings);

        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Legacy API
  version: 1.0.0
components:
  schemas:
    Subject:
      type: object
      properties:
        name:
          type: string
          $recursiveRef: '#/name'
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);
        self::assertSame([], $vocabularyWarnings);
        self::assertCount(1, $legacyWarnings);
        self::assertInstanceOf(LegacyKeywordWarningEvent::class, $legacyWarnings[0]);
        self::assertSame('$recursiveRef', $legacyWarnings[0]->legacyKeyword);
        self::assertSame('$dynamicRef', $legacyWarnings[0]->migrationKeyword);

        $subject = $validator->getDocument()->components?->schemas['Subject'];

        self::assertNotNull($subject);
        self::assertSame('#/name', $subject->properties['name']->extras['$recursiveRef'] ?? null);
    }

    #[Test]
    public function build_guards_vocabularies_outside_components(): void
    {
        $vocabularyWarnings = [];
        $legacyWarnings = [];
        $dispatcher = self::createWarningDispatcher($vocabularyWarnings, $legacyWarnings);

        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Path Vocabulary API
  version: 1.0.0
paths:
  /items:
    get:
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                $vocabulary:
                  'https://example.com/vocab/custom': false
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        self::assertCount(1, $vocabularyWarnings);
        self::assertSame('https://example.com/vocab/custom', $vocabularyWarnings[0]->vocabularyUri);
    }

    #[Test]
    public function build_ignores_vocabularies_of_registered_documents(): void
    {
        $registeredJson = <<<'JSON'
{
    "openapi": "3.1.0",
    "info": {"title": "Cross-Draft Resource", "version": "1.0.0"},
    "components": {
        "schemas": {
            "Remote": {
                "type": "object",
                "$vocabulary": {"https://example.com/vocab/custom": true}
            }
        }
    }
}
JSON;

        $mainYaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Clean API
  version: 1.0.0
components:
  schemas:
    Subject:
      type: object
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($mainYaml)
            ->withDocument('https://example.com/remote.json', $registeredJson)
            ->build();

        self::assertInstanceOf(OpenApiValidatorInterface::class, $validator);
    }

    /**
     * @param list<VocabularyDisabledWarningEvent> $vocabularyWarnings
     * @param list<LegacyKeywordWarningEvent> $legacyWarnings
     */
    private function buildVocabularySpec(
        string $uri,
        string $required,
        ?ArrayDispatcher $dispatcher = null,
    ): OpenApiValidatorInterface {
        $yaml = sprintf(
            <<<'YAML'
openapi: 3.1.0
info:
  title: Vocabulary API
  version: 1.0.0
components:
  schemas:
    Subject:
      type: object
      $vocabulary:
        '%s': %s
YAML,
            $uri,
            $required,
        );

        $builder = OpenApiValidatorBuilder::create()->fromYamlString($yaml);

        return null === $dispatcher
            ? $builder->build()
            : $builder->withEventDispatcher($dispatcher)->build();
    }

    /**
     * @param list<VocabularyDisabledWarningEvent> $vocabularyWarnings
     * @param list<LegacyKeywordWarningEvent> $legacyWarnings
     */
    private static function createWarningDispatcher(
        array &$vocabularyWarnings,
        array &$legacyWarnings,
    ): ArrayDispatcher {
        $vocabularyWarnings = [];
        $legacyWarnings = [];

        return new ArrayDispatcher([
            VocabularyDisabledWarningEvent::class => [
                static function (VocabularyDisabledWarningEvent $event) use (&$vocabularyWarnings): void {
                    $vocabularyWarnings[] = $event;
                },
            ],
            LegacyKeywordWarningEvent::class => [
                static function (LegacyKeywordWarningEvent $event) use (&$legacyWarnings): void {
                    $legacyWarnings[] = $event;
                },
            ],
        ]);
    }
}
