<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Duyler\OpenApi\Validator\Request\Internal\XmlMappingApplier;
use Duyler\OpenApi\Validator\Request\Internal\XmlValueReader;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ValueError;

use function strlen;
use function trim;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final readonly class XmlBodyParser
{
    private const int PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;
    private const int DEFAULT_MAX_XML_BYTES = 1_048_576;

    private readonly XmlMappingApplier $mappingApplier;

    public function __construct(
        private readonly int $maxXmlBytes = self::DEFAULT_MAX_XML_BYTES,
        private readonly ?LoggerInterface $logger = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->mappingApplier = new XmlMappingApplier($eventDispatcher);
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    public function parse(string $body, ?Schema $schema = null): array|string|null
    {
        $length = strlen($body);

        if ($this->maxXmlBytes < $length) {
            throw new BodyTooLargeException(
                actualBytes: $length,
                maxBytes: $this->maxXmlBytes,
            );
        }

        if ('' === trim($body)) {
            return '';
        }

        try {
            return LibxmlSecuredContext::run(function () use ($body, $schema): array|string|null {
                $xml = simplexml_load_string($body, options: self::PARSE_OPTIONS);

                if (false === $xml) {
                    return $body;
                }

                return null === $schema
                    ? XmlValueReader::read($xml)
                    : $this->mappingApplier->mapElement($xml, $schema);
            });
        } catch (ValueError $e) {
            $this->logger?->debug('XML body parse failure (ValueError), falling back to raw body', [
                'body_length' => $length,
                'exception' => $e,
            ]);

            return $body;
        }
    }
}
