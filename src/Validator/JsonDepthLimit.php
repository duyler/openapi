<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

/**
 * JSON parsing depth limit, classified by threat model.
 *
 * Selecting a case is the only way to set the depth passed to json_decode(),
 * which makes the rationale for the two distinct limits explicit at the type
 * level and prevents silent alignment to a value that is unsafe for one path.
 *
 * Untrusted (128): for user-controlled request bodies validated through
 * contentMediaType, where a deep payload is an inexpensive DoS vector.
 * Trusted (512): for OpenAPI specifications and already-sanitised internal
 * data, where deep nesting is expected and the input is not attacker-controlled.
 */
enum JsonDepthLimit: int
{
    case Untrusted = 128;

    case Trusted = 512;
}
