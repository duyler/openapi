<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * Mutable container holding resolved $ref targets for a single
 * OpenApiDocument. Stored as the WeakMap value in RefResolver::$cache
 * so that appending a new entry mutates the shared container instead
 * of triggering copy-on-write on a per-document array.
 */
final class RefCache
{
    /** @var array<string, Schema|Parameter|Response> */
    public array $map = [];
}
