<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal Marker for keyword validators that can declare whether they apply
 *           to a given Schema based on which keyword fields are populated. Used
 *           by {@see SchemaValidator} and by
 *           Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext to
 *           precompute a per-Schema subset of validators, so a node with no
 *           numeric constraints does not invoke NumericRangeValidator, etc.
 *           Implementations MUST be pure functions of the Schema argument: no
 *           configuration, no context, no side effects.
 */
interface KeywordApplicable extends SchemaValidatorInterface
{
    public function isApplicable(Schema $schema): bool;
}
