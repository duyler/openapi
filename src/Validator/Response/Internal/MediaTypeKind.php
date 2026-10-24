<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

/** @internal */
enum MediaTypeKind
{
    case Complete;
    case Sequential;
    case BinaryStream;
}
