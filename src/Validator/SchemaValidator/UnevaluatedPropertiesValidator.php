<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Override;

use function array_filter;
use function is_array;
use function is_string;

readonly class UnevaluatedPropertiesValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->unevaluatedProperties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $evaluatedProperties = $this->getEvaluatedProperties($schema, $data);
        $unevaluatedProperties = array_diff(array_keys($data), $evaluatedProperties);
        /** @var array<array-key, string> $stringUnevaluatedProperties */
        $stringUnevaluatedProperties = array_filter($unevaluatedProperties, is_string(...));

        if ($schema->unevaluatedProperties) {
            return;
        }

        if ([] !== $stringUnevaluatedProperties) {
            $dataPath = $this->getDataPath($context);
            $propertyName = array_values($stringUnevaluatedProperties)[0];
            throw new UnevaluatedPropertyError(
                dataPath: $dataPath,
                schemaPath: '/unevaluatedProperties',
                propertyName: $propertyName,
            );
        }
    }

    private function getEvaluatedProperties(Schema $schema, array $data): array
    {
        $evaluated = [];

        if (null !== $schema->properties) {
            $evaluated = [...$evaluated, ...array_keys($schema->properties)];
        }

        if (null !== $schema->patternProperties && [] !== $schema->patternProperties) {
            foreach (array_keys($data) as $propertyName) {
                if (false === is_string($propertyName)) {
                    continue;
                }

                foreach (array_keys($schema->patternProperties) as $pattern) {
                    if ('' === $pattern) {
                        continue;
                    }

                    if (preg_match($pattern, $propertyName)) {
                        $evaluated[] = $propertyName;
                    }
                }
            }
        }

        return array_unique($evaluated);
    }
}
