<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal Used internally by RefResolver; not part of the public API.
 */
final class RefCache
{
    /** @var array<string, Schema|Parameter|Response> */
    public array $map = [];
}
