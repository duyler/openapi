<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Closure;

final readonly class LibxmlSecuredContext
{
    /**
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public static function run(Closure $work): mixed
    {
        $previousInternalErrors = libxml_use_internal_errors(true);
        $previousLoader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static fn(): null => null);

        try {
            return $work();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
            libxml_set_external_entity_loader($previousLoader);
        }
    }
}
