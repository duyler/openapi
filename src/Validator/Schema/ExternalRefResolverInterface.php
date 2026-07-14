<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;

interface ExternalRefResolverInterface
{
    /**
     * Resolves an external $ref to a Schema instance.
     *
     * Implementations are responsible for fetching the referenced document,
     * extracting the schema, and applying security policies (allow-list,
     * timeout, SSRF protection). Throws on failure.
     */
    public function resolve(string $ref): Schema;
}
