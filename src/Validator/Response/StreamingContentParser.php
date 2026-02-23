<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use JsonException;

use function assert;
use function is_array;
use function is_null;
use function is_scalar;
use function strlen;

use const JSON_THROW_ON_ERROR;

final readonly class StreamingContentParser
{
    /**
     * Parse streaming content based on content type
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parse(string $body, string $contentType): array
    {
        return match (true) {
            str_contains($contentType, 'application/jsonl'),
            str_contains($contentType, 'application/x-ndjson') => $this->parseJsonLines($body),
            str_contains($contentType, 'text/event-stream') => $this->parseServerSentEvents($body),
            str_contains($contentType, 'application/json-seq') => $this->parseJsonSeq($body),
            default => [],
        };
    }

    /**
     * Parse JSON Lines (NDJSON) format
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonLines(string $body): array
    {
        $lines = explode("\n", trim($body));
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];

        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                try {
                    /** @var array<int|string, mixed> $decoded */
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $items[] = $decoded;
                } catch (JsonException) {
                    $items[] = null;
                }
            }
        }

        return $items;
    }

    /**
     * Parse Server-Sent Events format
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseServerSentEvents(string $body): array
    {
        /** @var list<array<int|string, mixed>|null> $events */
        $events = [];
        $currentEvent = [];

        foreach (explode("\n", $body) as $line) {
            if ('' === $line) {
                if ([] !== $currentEvent) {
                    $events[] = $this->formatSseEvent($currentEvent);
                    $currentEvent = [];
                }
                continue;
            }

            if (str_starts_with($line, ':')) {
                continue;
            }

            $colonPos = strpos($line, ':');
            if (false !== $colonPos) {
                $field = substr($line, 0, $colonPos);
                $value = ltrim(substr($line, $colonPos + 1));
                $currentEvent[$field] = $value;
            }
        }

        if ([] !== $currentEvent) {
            $events[] = $this->formatSseEvent($currentEvent);
        }

        return $events;
    }

    /**
     * Parse JSON Text Sequences (RFC 7464)
     *
     * @return list<array<int|string, mixed>|null>
     */
    public function parseJsonSeq(string $body): array
    {
        /** @var list<array<int|string, mixed>|null> $items */
        $items = [];
        $pos = 0;
        $length = strlen($body);

        while ($pos < $length) {
            if (substr($body, $pos, 1) === "\x1E") {
                $pos++;
            }

            $endPos = strpos($body, "\x1E", $pos);
            if (false === $endPos) {
                $endPos = $length;
            }

            $json = substr($body, $pos, $endPos - $pos);
            $json = trim($json);

            if ('' !== $json) {
                try {
                    /** @var array<int|string, mixed> $decoded */
                    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    $items[] = $decoded;
                } catch (JsonException) {
                    $items[] = null;
                }
            }

            $pos = $endPos;
        }

        return $items;
    }

    /**
     * Format SSE event data
     *
     * @param array<string, string> $event
     * @return array<int|string, mixed>
     */
    private function formatSseEvent(array $event): array
    {
        $result = [];

        if (isset($event['event'])) {
            $result['event'] = $event['event'];
        }
        if (isset($event['data'])) {
            $dataValue = $event['data'];
            try {
                $decoded = json_decode($event['data'], true, 512, JSON_THROW_ON_ERROR);
                assert(is_array($decoded) || is_null($decoded) || is_scalar($decoded));
                $dataValue = $decoded;
            } catch (JsonException) {
            }
            $result['data'] = $dataValue;
        }
        if (isset($event['id'])) {
            $result['id'] = $event['id'];
        }

        return $result;
    }
}
