<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

enum EmptyArrayStrategy
{
    case PreferArray;
    case PreferObject;
    case Reject;
    case AllowBoth;
}
