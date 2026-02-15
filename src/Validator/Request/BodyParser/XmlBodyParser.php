<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use ValueError;

use function is_array;

readonly class XmlBodyParser
{
    /**
     * @return array<array-key, mixed>|string
     */
    public function parse(string $body): array|string
    {
        if ('' === trim($body)) {
            return '';
        }

        libxml_set_external_entity_loader(null);
        libxml_use_internal_errors(true);

        try {
            /**
             * IMPORTANT: Never add LIBXML_NOENT flag here.
             * Adding LIBXML_NOENT would enable entity substitution and allow
             * XXE attacks even with external entity loader disabled.
             */
            $xml = simplexml_load_string($body);

            if (false === $xml) {
                return $body;
            }

            $encoded = json_encode($xml);
            if (false === $encoded) {
                return $body;
            }

            $decoded = json_decode($encoded, true);
            if (false === is_array($decoded)) {
                return $body;
            }

            return $decoded;
        } catch (ValueError) {
            return $body;
        } finally {
            libxml_clear_errors();
        }
    }
}
