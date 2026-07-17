<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Closure;

/**
 * Hardened libxml runtime context for XXE-safe XML parsing.
 */
final readonly class LibxmlSecuredContext
{
    /**
     * Runs the given work with a hardened libxml configuration that prevents
     * XML External Entity (XXE) attacks and isolates libxml global state from
     * the surrounding process.
     *
     * Installs a deny-all external entity loader (so SYSTEM/PUBLIC references
     * cannot resolve file://, http://, or any other scheme), enables internal
     * error buffering, and restores both libxml_use_internal_errors and the
     * previous external entity loader in finally — even if the work throws.
     * This encapsulation is mandatory for long-running runtimes (RoadRunner,
     * FrankenPHP, Swoole) where libxml state is shared across requests and
     * coroutines, so adjacent XML parsing must not be left with a deny-all
     * loader after this helper returns.
     *
     * The work closure is responsible only for the actual parsing call,
     * including the LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING flags
     * via the options named argument of simplexml_load_string. Never combine
     * this with LIBXML_NOENT — entity substitution must stay off, otherwise
     * the deny-all loader remains the only line of defense.
     *
     * @template T
     *
     * @param Closure(): T $work work that performs the libxml parsing
     *
     * @return T whatever the work closure returns
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
