<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Example;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Example;

use function is_array;
use function json_encode;

final readonly class ExampleValidator
{
    /**
     * @return list<ExampleWarning>
     */
    public function validate(mixed $data, Schema $schema): array
    {
        /** @var list<mixed> $examples */
        $examples = $this->collectExamples($schema);

        if ([] === $examples) {
            return [];
        }

        foreach ($examples as $example) {
            if ($this->matchesExample($data, $example)) {
                return [];
            }
        }

        $encoded = json_encode($data);

        if (false === $encoded) {
            $encoded = '(unable to encode data)';
        }

        return [
            new ExampleWarning(
                $encoded,
                'Data does not match any of the declared examples',
            ),
        ];
    }

    /**
     * @param MediaType $mediaType
     *
     * @return list<ExampleWarning>
     */
    public function validateMediaType(mixed $data, MediaType $mediaType): array
    {
        /** @var list<mixed> $examples */
        $examples = $this->collectMediaTypeExamples($mediaType);

        if ([] === $examples) {
            return [];
        }

        foreach ($examples as $example) {
            if ($this->matchesExample($data, $example)) {
                return [];
            }
        }

        $encoded = json_encode($data);

        if (false === $encoded) {
            $encoded = '(unable to encode data)';
        }

        return [
            new ExampleWarning(
                $encoded,
                'Data does not match any of the declared media type examples',
            ),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function collectExamples(Schema $schema): array
    {
        /** @var list<mixed> $examples */
        $examples = [];

        if (null !== $schema->example) {
            $examples[] = $schema->example;
        }

        if (null !== $schema->examples) {
            $examples = $this->extractExampleValues($schema->examples, $examples);
        }

        return $examples;
    }

    /**
     * @param MediaType $mediaType
     *
     * @return list<mixed>
     */
    private function collectMediaTypeExamples(MediaType $mediaType): array
    {
        /** @var list<mixed> $examples */
        $examples = [];

        $example = $mediaType->example;

        if (null !== $example && null !== $example->value) {
            $examples[] = $example->value;
        }

        if (null !== $mediaType->examples) {
            $examples = $this->extractExampleValues($mediaType->examples, $examples);
        }

        return $examples;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<mixed> $target
     *
     * @return list<mixed>
     */
    private function extractExampleValues(array $source, array $target): array
    {
        foreach ($source as $exampleEntry) {
            if ($exampleEntry instanceof Example && null !== $exampleEntry->value) {
                $target[] = $exampleEntry->value;
            } elseif (is_array($exampleEntry) && isset($exampleEntry['value'])) {
                $target[] = $exampleEntry['value'];
            }
        }

        return $target;
    }

    private function matchesExample(mixed $data, mixed $example): bool
    {
        if ($data === $example) {
            return true;
        }

        if (is_array($data) && is_array($example)) {
            $dataEncoded = json_encode($data);
            $exampleEncoded = json_encode($example);

            return false !== $dataEncoded && $dataEncoded === $exampleEncoded;
        }

        return false;
    }
}
