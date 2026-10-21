<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

use function array_filter;
use function array_values;

/** @internal */
final readonly class ApplicableValidators
{
    /**
     * @param list<SchemaValidatorInterface> $validators
     * @return list<SchemaValidatorInterface>
     */
    public static function filter(array $validators, Schema $schema): array
    {
        /** @var list<SchemaValidatorInterface> $applicable */
        $applicable = array_values(array_filter(
            $validators,
            static function (SchemaValidatorInterface $validator) use ($schema): bool {
                return false === ($validator instanceof KeywordApplicable) || $validator->isApplicable($schema);
            },
        ));

        return $applicable;
    }
}
