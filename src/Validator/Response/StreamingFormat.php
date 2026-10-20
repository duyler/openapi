<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

enum StreamingFormat
{
    case NdJson;
    case Sse;
    case JsonSeq;
}
