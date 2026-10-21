<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

enum JsonDepthLimit: int
{
    case Untrusted = 128;

    case Trusted = 512;
}
