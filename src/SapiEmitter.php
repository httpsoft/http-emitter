<?php

declare(strict_types=1);

namespace HttpSoft\Runner;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function flush;
use function ob_get_length;
use function ob_get_level;
use function preg_match;
use function strlen;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

final class SapiEmitter implements EmitterInterface
{
    /**
     * @var int|null
     */
    private ?int $bufferLength;

    /**
     * @param int|null $bufferLength
     * @throws InvalidArgumentException if buffer length is integer type and less than or one.
     */
    public function __construct(int $bufferLength = null)
    {
        if ($bufferLength !== null && $bufferLength < 1) {
            throw new InvalidArgumentException('Buffer length must be greater than zero.');
        }

        $this->bufferLength = $bufferLength;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException if headers already sent or output has been emitted previously.
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent() || (ob_get_level() > 0 && ob_get_length() > 0)) {
            throw new RuntimeException(
                'Unable to emit response. Headers already sent or output has been emitted previously.'
            );
        }

        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        $this->emitBody($response);
    }

    /**
     * Loops through and emits each header as specified to `Psr\Http\Message\MessageInterface::getHeaders()`.
     *
     * @param ResponseInterface $response
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
            $firstReplace = ($name === 'Set-Cookie') ? false : true;

            foreach ($values as $value) {
                header("{$name}: {$value}", $firstReplace);
                $firstReplace = false;
            }
        }
    }

    /**
     * Emits the response status line.
     *
     * @param ResponseInterface $response
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = (int) $response->getStatusCode();
        $reasonPhrase = trim((string) $response->getReasonPhrase());
        $protocolVersion = trim((string) $response->getProtocolVersion());

        $status = $statusCode . (!$reasonPhrase ? '' : " {$reasonPhrase}");
        header("HTTP/{$protocolVersion} {$status}", true, $statusCode);
    }

    /**
     * Emits the message body.
     *
     * @param ResponseInterface $response
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if (!$body->isReadable()) {
            echo '';
            return;
        }

        if ($this->bufferLength === null) {
            echo $response->getBody();
            return;
        }

        flush();
        $range = $this->parseContentRange($response->getHeaderLine('content-range'));

        if (isset($range['unit']) && $range['unit'] === 'bytes') {
            $this->emitBodyRange($body, $range['first'], $range['last']);
            return;
        }

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read($this->bufferLength);
        }
    }

    /**
     * Emits a range of the message body.
     *
     * @param StreamInterface $body
     * @param int $first
     * @param int $last
     */
    private function emitBodyRange(StreamInterface $body, int $first, int $last): void
    {
        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
        }

        while ($length >= $this->bufferLength && !$body->eof()) {
            $contents = $body->read($this->bufferLength);
            $length -= strlen($contents);
            echo $contents;
        }

        if ($length > 0 && !$body->eof()) {
            echo $body->read($length);
        }
    }

    /**
     * Parse Content-Range header.
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16
     * @param string $header
     * @return array|null
     */
    private function parseContentRange(string $header): ?array
    {
        if (preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
            return [
                'unit' => $matches['unit'],
                'first' => (int) $matches['first'],
                'last' => (int) $matches['last'],
                'length' => ($matches['length'] === '*') ? '*' : (int) $matches['length'],
            ];
        }

        return null;
    }
}
