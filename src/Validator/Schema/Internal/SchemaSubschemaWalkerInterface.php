<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Generator;

/** @internal */
interface SchemaSubschemaWalkerInterface
{
    /**
     * @return Generator<string, Schema, void, void> the key is the relative JSON Pointer segment from the parent schema
     */
    public function iterateSubSchemas(Schema $schema): Generator;

    /**
     * @return list<Schema>
     */
    public function collectSingleSubSchemas(Schema $schema): array;
}
