<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
interface KeywordApplicable extends SchemaValidatorInterface
{
    public function isApplicable(Schema $schema): bool;
}
