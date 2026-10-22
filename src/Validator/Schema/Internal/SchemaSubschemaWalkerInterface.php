<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Generator;

/** @internal */
interface SchemaSubschemaWalkerInterface
{
    /**
     * @return Generator<array-key, Schema, void, void>
     */
    public function iterateSubSchemas(Schema $schema): Generator;

    /**
     * @return list<Schema>
     */
    public function collectSingleSubSchemas(Schema $schema): array;
}
