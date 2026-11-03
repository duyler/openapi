<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Test\Support\FixtureLocator;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

use function array_map;
use function file_get_contents;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class AppendixCDynamicRefGoldenTest extends TestCase
{
    private const string TREE_URI = 'https://example.com/tree';

    private const string STRICT_TREE_URI = 'https://example.com/strict-tree';

    private const string SUBJECT_REF = '#/components/schemas/Subject';

    private const string INSTANCE_WITH_TYPO = '{"children":[{"data":1,"children":[{"daat":1}]}]}';

    /**
     * @var array<string, string>
     */
    private const array DOCUMENT_FIXTURES = [
        self::TREE_URI => 'appendix-c/tree.json',
        self::STRICT_TREE_URI => 'appendix-c/strict-tree.json',
    ];

    #[Test]
    public function misspelled_inner_field_is_valid_against_tree(): void
    {
        $validator = $this->validatorWithSubjectRef(self::TREE_URI);

        $validator->validateSchema($this->instanceWithTypo(), self::SUBJECT_REF);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function misspelled_inner_field_is_invalid_against_strict_tree(): void
    {
        $validator = $this->validatorWithSubjectRef(self::STRICT_TREE_URI);

        try {
            $validator->validateSchema($this->instanceWithTypo(), self::SUBJECT_REF);
        } catch (ValidationException $exception) {
            $this->assertDamatPropertyReported($exception);

            return;
        }

        self::fail('Expected the misspelled daat property to be rejected by strict-tree');
    }

    #[Test]
    public function well_formed_inner_field_is_valid_against_strict_tree(): void
    {
        $validator = $this->validatorWithSubjectRef(self::STRICT_TREE_URI);

        $validator->validateSchema(
            ['children' => [['data' => 1, 'children' => [['data' => 2]]]]],
            self::SUBJECT_REF,
        );

        self::addToAssertionCount(1);
    }

    #[Test]
    public function plain_name_fallback_selects_anchor_by_registration_order(): void
    {
        $envelope = static function (string $uri, string $anchorType): string {
            return json_encode([
                'openapi' => '3.1.0',
                'info' => ['title' => $uri, 'version' => '1.0.0'],
                '$self' => $uri,
                'components' => ['schemas' => ['Subject' => [
                    '$id' => $uri,
                    '$dynamicAnchor' => 'dup',
                    'type' => $anchorType,
                ]]],
            ], JSON_THROW_ON_ERROR);
        };

        $subject = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            'components' => ['schemas' => ['Subject' => ['$dynamicRef' => '#dup']]],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument('https://example.com/first', $envelope('https://example.com/first', 'string'))
            ->withDocument('https://example.com/second', $envelope('https://example.com/second', 'integer'))
            ->fromJsonString(json_encode($subject, JSON_THROW_ON_ERROR))
            ->build();

        $validator->validateSchema('ok', '#/components/schemas/Subject');

        $this->expectException(ValidationException::class);

        $validator->validateSchema(42, '#/components/schemas/Subject');
    }

    #[Test]
    public function ref_to_unregistered_document_fails_closed(): void
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'appendix-c', 'version' => '1.0.0'],
            'components' => ['schemas' => ['Subject' => ['$ref' => self::TREE_URI]]],
        ];

        $builder = OpenApiValidatorBuilder::create()->withDocument(
            self::STRICT_TREE_URI,
            $this->envelope(self::STRICT_TREE_URI),
        );

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('https://example.com/tree');

        $builder->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))->build();
    }

    private function assertDamatPropertyReported(ValidationException $exception): void
    {
        $errors = $exception->getErrors();
        self::assertNotEmpty($errors);

        $propertyNames = array_map(
            static fn(UnevaluatedPropertyError $error): string => (string) ($error->params()['propertyName'] ?? ''),
            $this->unevaluatedErrors($errors),
        );

        self::assertContains('daat', $propertyNames);
        self::assertNotContains('data', $propertyNames);
    }

    /**
     * @param list<ValidationErrorInterface> $errors
     * @return list<UnevaluatedPropertyError>
     */
    private function unevaluatedErrors(array $errors): array
    {
        $unevaluated = [];

        foreach ($errors as $error) {
            if ($error instanceof UnevaluatedPropertyError) {
                $unevaluated[] = $error;
            }
        }

        self::assertNotEmpty($unevaluated, 'Expected an unevaluated property error');

        return $unevaluated;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function instanceWithTypo(): array
    {
        /** @var array<array-key, mixed> $instance */
        $instance = json_decode(self::INSTANCE_WITH_TYPO, true, 512, JSON_THROW_ON_ERROR);

        return $instance;
    }

    private function validatorWithSubjectRef(string $subjectRef): OpenApiValidatorInterface
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'appendix-c', 'version' => '1.0.0'],
            'components' => ['schemas' => ['Subject' => ['$ref' => $subjectRef]]],
        ];

        $builder = OpenApiValidatorBuilder::create();

        foreach (self::DOCUMENT_FIXTURES as $uri => $relativeFixturePath) {
            $builder = $builder->withDocument($uri, $this->envelopeWithSchema($uri, $relativeFixturePath));
        }

        return $builder
            ->fromJsonString(json_encode($document, JSON_THROW_ON_ERROR))
            ->build();
    }

    private function envelopeWithSchema(string $uri, string $relativeFixturePath): string
    {
        /** @var array<array-key, mixed> $schema */
        $schema = json_decode(
            (string) file_get_contents(FixtureLocator::path($relativeFixturePath)),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $this->envelope($uri, $schema);
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function envelope(string $uri, array $schema = []): string
    {
        return json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'appendix-c', 'version' => '1.0.0'],
            '$self' => $uri,
            'components' => ['schemas' => ['Subject' => $schema]],
        ], JSON_THROW_ON_ERROR);
    }
}
