<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

enum ValidatorMode
{
    case Request;
    case Response;
}
