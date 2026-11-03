<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

/**
 * Distinguishes JSON arrays from JSON objects for instance data,
 * because a decoded PHP array cannot carry that distinction.
 */
enum JsonContainerKind
{
    case Array;

    case Object;
}
