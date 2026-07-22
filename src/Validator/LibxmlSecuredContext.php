<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Closure;

/**
 * Hardened libxml runtime context for XXE-safe XML parsing.
 *
 * @danger NOT_THREAD_SAFE
 *
 * libxml_use_internal_errors and libxml_set_external_entity_loader are
 * process-global. Under Swoole coroutines the capture/restore sequence
 * in {@see run()} races with concurrent XML parsing in other coroutines
 * (O-006, S-011). Two failure modes:
 *   1. Coroutine A installs deny-all loader; B captures it as previous;
 *      A restores; B restores to deny-all -> process-wide XML parsing
 *      left without an entity loader.
 *   2. A installs deny-all; B yields inside $work; A restores to the
 *      default loader; B's $work sees the default loader -> XXE bypass
 *      for B.
 *
 * Recommended mitigations: restrict XML body validation to prefork
 * workers, or delegate XML parsing to an isolated Swoole\Process worker.
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
