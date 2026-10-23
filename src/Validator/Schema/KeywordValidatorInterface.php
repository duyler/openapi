<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;

/**
 * Public contract for validators of a single schema keyword.
 *
 * Implementations are registered in KeywordValidatorRegistry under the keyword
 * they validate. Registration order is dispatch order, so validators that read
 * evaluation state (for example unevaluatedProperties) must stay registered
 * before the keywords that depend on them. Custom keyword validators are
 * always appended after the built-in set, which means applicator-style custom
 * keywords cannot place themselves ahead of the unevaluated* validators.
 */
interface KeywordValidatorInterface
{
    /**
     * Whether this validator participates in validating the given schema.
     */
    public function appliesTo(Schema $schema): bool;

    public function validate(mixed $data, Schema $schema, ValidationContext $context): void;
}
