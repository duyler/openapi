<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Exception\MultipartBodyTooLargeException;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\Internal\MultipartStreamSplitter;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

use function array_fill;
use function array_map;
use function array_pad;
use function count;
use function iterator_to_array;
use function preg_match;
use function preg_replace;
use function str_repeat;
use function str_split;
use function strlen;

#[CoversClass(MultipartStreamSplitter::class)]
final class MultipartStreamSplitterTest extends TestCase
{
    private const string BOUNDARY = 'sep';

    private const string CONTENT_TYPE = 'multipart/mixed; boundary=' . self::BOUNDARY;

    #[Test]
    public function well_formed_body_splits_into_header_content_pairs(): void
    {
        $body = "--sep\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "hello\r\n"
            . "--sep\r\n"
            . "Content-Type: image/png\r\n"
            . "\r\n"
            . "PNG\r\n"
            . "--sep--";

        $parts = $this->split($body, 64);

        self::assertSame(
            [
                ['headers' => "\r\nContent-Type: text/plain", 'content' => 'hello'],
                ['headers' => "\r\nContent-Type: image/png", 'content' => 'PNG'],
            ],
            $parts,
        );
    }

    #[Test]
    public function generator_returns_the_consumed_byte_count(): void
    {
        $body = "--sep\r\n\r\n\r\nvalue\r\n--sep--";

        $generator = new MultipartStreamSplitter(self::CONTENT_TYPE)->parts($this->stream($body, 3));

        iterator_to_array($generator);

        self::assertSame(strlen($body), $generator->getReturn());
    }

    #[Test]
    public function delimiter_sequence_inside_binary_content_does_not_split(): void
    {
        $binary = "raw\r\n--sepJUNK-with-\r\n\r\nbytes\x89\x50\r\ntail";

        $body = "--sep\r\n"
            . "Content-Type: image/png\r\n"
            . "\r\n"
            . $binary . "\r\n"
            . "--sep--";

        $parts = $this->split($body, 7);

        self::assertCount(1, $parts);
        self::assertSame($binary, $parts[0]['content']);
    }

    #[Test]
    public function close_marker_with_a_garbage_tail_inside_content_does_not_close(): void
    {
        $binary = "good\0bytes\r\n--sep--XJUNK-not-a-close\r\nEVIL-PART-2-CONTENT";

        $body = "--sep\r\n"
            . "Content-Type: image/png\r\n"
            . "\r\n"
            . $binary . "\r\n"
            . "--sep\r\n"
            . "\r\n"
            . "second\r\n"
            . "--sep--";

        $parts = $this->split($body, 9);

        self::assertCount(2, $parts);
        self::assertSame($binary, $parts[0]['content']);
        self::assertSame('second', $parts[1]['content']);
    }

    #[Test]
    public function close_delimiter_with_transport_padding_and_epilogue_is_accepted(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep-- \t\r\n"
            . "epilogue noise";

        $parts = $this->split($body, 5);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function chunk_break_inside_the_close_tail_waits_for_the_next_chunk(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--\r\n"
            . "epilogue";

        $chunkSize = strlen($body) - strlen("\r\nepilogue");

        self::assertSame([['headers' => '', 'content' => 'value']], $this->split($body, $chunkSize));
    }

    #[Test]
    public function garbage_after_a_chunk_split_close_marker_keeps_the_part_open(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--JUNK\r\n"
            . "still content\r\n"
            . "--sep--";

        $chunkSize = strlen($body) - strlen("JUNK\r\nstill content\r\n--sep--");

        $parts = $this->split($body, $chunkSize);

        self::assertCount(1, $parts);
        self::assertSame("value\r\n--sep--JUNK\r\nstill content", $parts[0]['content']);
    }

    #[Test]
    public function bare_close_delimiter_with_padding_at_end_of_stream_is_accepted(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . '--sep--  ';

        $parts = $this->split($body, 4);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function garbage_after_the_close_marker_at_end_of_stream_stays_in_the_part_content(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . '--sep--JUNK';

        $parts = $this->split($body, 4);

        self::assertCount(1, $parts);
        self::assertSame("value\r\n--sep--JUNK", $parts[0]['content']);
    }

    #[Test]
    public function preamble_close_delimiter_with_a_line_ending_yields_no_parts(): void
    {
        self::assertSame([], $this->split("--sep--\r\nepilogue noise", 6));
    }

    #[Test]
    public function cr_without_lf_after_a_delimiter_candidate_is_not_a_delimiter(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep\rX\r\n"
            . "--sep--";

        $parts = $this->split($body, 7);

        self::assertCount(1, $parts);
        self::assertSame("value\r\n--sep\rX", $parts[0]['content']);
    }

    #[Test]
    public function close_candidate_with_only_padding_at_end_of_stream_stays_in_the_part_content(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . '--sep  ';

        $parts = $this->split($body, 5);

        self::assertCount(1, $parts);
        self::assertSame("value\r\n--sep  ", $parts[0]['content']);
    }

    #[Test]
    public function boundary_prefix_lines_do_not_create_phantom_parts(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sepPrefix\r\n"
            . "--sep--";

        $parts = $this->split($body, 5);

        self::assertSame([['headers' => '', 'content' => "value\r\n--sepPrefix"]], $parts);
    }

    #[Test]
    public function streamed_parts_match_the_buffered_parser_shapes(): void
    {
        $body = "--sep\r\n"
            . "Content-Type: application/json\r\n"
            . "\r\n"
            . '{"a":1}' . "\r\n"
            . "--sep\r\n"
            . "Content-Disposition: form-data; name=\"doc\"\r\n"
            . "\r\n"
            . "text body\r\n"
            . "--sep--";

        $streamed = $this->split($body, 9);
        $buffered = new MultipartBodyParser()->parse($body, self::CONTENT_TYPE);

        self::assertCount(2, $buffered);
        self::assertSame(
            array_map(
                static fn(array $part): array => [
                    'headers' => $part['headers'],
                    'content' => preg_replace("/\r\n\z/", '', $part['content']) ?? $part['content'],
                ],
                $buffered,
            ),
            $streamed,
        );
    }

    #[Test]
    public function preamble_without_a_real_delimiter_is_discarded(): void
    {
        $body = "boundary=sep noise\r\n\r\n"
            . "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 6);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function chunks_split_inside_the_boundary_token_keep_the_part_intact(): void
    {
        $body = "--sep\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 1);

        self::assertSame(
            [['headers' => "\r\nContent-Type: text/plain", 'content' => 'value']],
            $parts,
        );
    }

    #[Test]
    public function boundary_is_inferred_from_the_body_when_the_header_lacks_it(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 4, 'multipart/mixed');

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function body_without_any_delimiter_yields_no_parts(): void
    {
        self::assertSame([], $this->split('not multipart framed content', 5));
    }

    #[Test]
    public function empty_stream_yields_no_parts(): void
    {
        self::assertSame([], $this->split('', 8));
    }

    #[Test]
    public function unterminated_final_part_is_still_emitted(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . 'value';

        $parts = $this->split($body, 3);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function transport_padding_before_the_delimiter_line_is_tolerated(): void
    {
        $body = "--sep \t\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 3);

        self::assertSame([['headers' => " \t", 'content' => 'value']], $parts);
    }

    #[Test]
    public function bare_lf_delimiter_wannabes_do_not_split(): void
    {
        $body = "--sep\r\n"
            . "\r\n"
            . "value\n--sep\nmore\r\n"
            . "--sep--";

        $parts = $this->split($body, 4);

        self::assertCount(1, $parts);
        self::assertSame("value\n--sep\nmore", $parts[0]['content']);
    }

    #[Test]
    public function single_junk_character_before_a_bare_lf_does_not_split(): void
    {
        $binary = "value\r\n--sepX\nstill the same part\r\n--sep--";

        $body = "--sep\r\n"
            . "\r\n"
            . $binary;

        $parts = $this->split($body, 9);

        self::assertSame([['headers' => '', 'content' => "value\r\n--sepX\nstill the same part"]], $parts);
    }

    #[Test]
    public function part_count_over_the_cap_raises_the_domain_exception(): void
    {
        $body = "--sep\r\n\r\nx\r\n--sep--";

        $this->expectException(MultipartBodyTooLargeException::class);

        iterator_to_array(
            new MultipartStreamSplitter(self::CONTENT_TYPE, maxParts: 0)->parts($this->stream($body, 64)),
        );
    }

    #[Test]
    public function unclosed_final_part_over_the_part_cap_raises_the_domain_exception(): void
    {
        $body = "--sep\r\n\r\none\r\n--sep\r\n\r\ntwo";

        $this->expectException(MultipartBodyTooLargeException::class);

        iterator_to_array(
            new MultipartStreamSplitter(self::CONTENT_TYPE, maxParts: 1)->parts($this->stream($body, 64)),
        );
    }

    #[Test]
    public function part_content_over_the_byte_budget_raises_the_body_too_large_exception(): void
    {
        $body = "--sep\r\n\r\n" . str_repeat('a', 64) . "\r\n--sep--";

        $this->expectException(BodyTooLargeException::class);

        iterator_to_array(
            new MultipartStreamSplitter(self::CONTENT_TYPE, maxParts: 10, maxPartBytes: 16)
                ->parts($this->stream($body, 9)),
        );
    }

    #[Test]
    public function multiple_parts_arrive_in_stream_order(): void
    {
        $parts = [];
        $body = '';

        for ($index = 0; $index < 5; ++$index) {
            $body .= "--sep\r\nContent-Disposition: form-data; name=\"f{$index}\"\r\n\r\npayload {$index}\r\n";
        }

        $body .= '--sep--';

        foreach ($this->generator($body, 11) as $part) {
            $parts[] = $part;
        }

        self::assertCount(5, $parts);
        self::assertSame('f0', $this->dispositionName($parts[0]['headers']));
        self::assertSame('f4', $this->dispositionName($parts[4]['headers']));
        self::assertSame('payload 0', $parts[0]['content']);
        self::assertSame('payload 4', $parts[4]['content']);
    }

    #[Test]
    public function immediate_close_delimiter_yields_no_parts(): void
    {
        self::assertSame([], $this->split('--sep--', 3));
    }

    #[Test]
    public function stream_without_a_resolvable_boundary_yields_no_parts(): void
    {
        self::assertSame([], $this->split('garbage without any framing', 7, 'multipart/mixed'));
    }

    #[Test]
    public function preamble_delimiter_candidates_without_a_valid_tail_are_skipped(): void
    {
        $body = "--sepJUNK line\r\n"
            . "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 6);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function delimiter_truncated_at_end_of_stream_yields_no_parts(): void
    {
        self::assertSame([], $this->split('--sep', 2));
        self::assertSame([], $this->split('--sep-', 4));
    }

    #[Test]
    public function blank_parts_between_delimiters_are_skipped(): void
    {
        $body = "--sep\r\n\r\n\r\n--sep\r\n\r\nvalue\r\n--sep--";

        $parts = $this->split($body, 4);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function unanchored_preamble_occurrences_are_skipped_during_the_search(): void
    {
        $body = "xx--sepYY\r\n"
            . "--sepJUNK\r\n"
            . "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 5);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    #[Test]
    public function delimiter_line_truncated_inside_padding_or_cr_yields_no_parts(): void
    {
        self::assertSame([], $this->split("--sep \t ", 3));
        self::assertSame([], $this->split("--sep \r", 3));
    }

    #[Test]
    public function malformed_part_without_a_header_separator_is_skipped(): void
    {
        $body = "--sep\r\n"
            . "no-separator\r\n"
            . "--sep\r\n"
            . "\r\n"
            . "value\r\n"
            . "--sep--";

        $parts = $this->split($body, 4);

        self::assertSame([['headers' => '', 'content' => 'value']], $parts);
    }

    /**
     * @return list<array{headers: string, content: string}>
     */
    private function split(string $body, int $chunkSize, string $contentType = self::CONTENT_TYPE): array
    {
        return iterator_to_array($this->generator($body, $chunkSize, $contentType));
    }

    /**
     * @return Generator<int, array{headers: string, content: string}, void, int>
     */
    private function generator(string $body, int $chunkSize, string $contentType = self::CONTENT_TYPE): Generator
    {
        return new MultipartStreamSplitter($contentType)->parts($this->stream($body, $chunkSize));
    }

    private function dispositionName(string $headers): string
    {
        preg_match('/name="(?<name>[^"]+)"/', $headers, $matches);

        return $matches['name'] ?? '';
    }

    private function stream(string $body, int $chunkSize): StreamInterface
    {
        $chunks = '' === $body ? [''] : str_split($body, $chunkSize);
        $eofSequence = array_fill(0, count($chunks), false);
        $eofSequence[] = true;

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(strlen($body));
        $stream->method('eof')->willReturnOnConsecutiveCalls(...$eofSequence);
        $stream->method('read')->willReturnOnConsecutiveCalls(...array_pad($chunks, count($chunks) + 1, ''));

        return $stream;
    }
}
