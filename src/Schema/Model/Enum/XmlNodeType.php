<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Enum;

enum XmlNodeType: string
{
    case Element = 'element';
    case Attribute = 'attribute';
    case Text = 'text';
    case Cdata = 'cdata';
    case None = 'none';
}
