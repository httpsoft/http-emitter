<?php

declare(strict_types=1);

namespace HttpSoft\Tests\Emitter;

use HttpSoft\Emitter\EmitterInterface;
use HttpSoft\Emitter\Exception\HeadersAlreadySentException;
use HttpSoft\Emitter\Exception\OutputAlreadySentException;
use HttpSoft\Emitter\SapiEmitter;
use HttpSoft\Message\Response;
use HttpSoft\Tests\Emitter\TestAsset\MockData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_filter;
use function fopen;
use function HttpSoft\Emitter\header;
use function HttpSoft\Emitter\headers_list;
use function HttpSoft\Emitter\http_response_code;
use function HttpSoft\Emitter\http_response_status_line;
use function implode;
use function is_int;
use function preg_replace;
use function strlen;

class SapiEmitterTest extends TestCase
{
    public function setUp(): void
    {
        require 'TestAsset/SapiFunctionMocks.php';
        MockData::reset();
    }

    public function testEmitDefault(): void
    {
        $response = $this->createResponse();
        (new SapiEmitter())->emit($response);

        $this->assertSame(200, http_response_code());
        $this->assertCount(0, headers_list());
        $this->assertSame([], headers_list());
        $this->assertSame('HTTP/1.1 200 OK', http_response_status_line());
        $this->expectOutputString('');
    }

    public function testEmitWithSpecifyArguments(): void
    {
        $response = $this->createResponse($code = 404, ['X-Test' => 'test'], $contents = 'Page not found', '2');
        (new SapiEmitter())->emit($response);

        $this->assertSame($code, http_response_code());
        $this->assertCount(1, headers_list());
        $this->assertSame(['X-Test: test'], headers_list());
        $this->assertSame('HTTP/2 404 Not Found', http_response_status_line());
        $this->expectOutputString($contents);
    }

    public function testEmitDuplicateHeadersNotReplaced(): void
    {
        $response = $this->createResponse($code = 200, ['X-Test' => 'test-1'], $contents = 'Contents')
            ->withAddedHeader('X-Test', 'test-2')
            ->withAddedHeader('X-Test', 'test-3')
            ->withAddedHeader('Set-Cookie', 'key-1=value-1')
            ->withAddedHeader('Set-Cookie', 'key-2=value-2')
        ;

        (new SapiEmitter())->emit($response);

        $expectedHeaders = [
            'X-Test: test-1',
            'X-Test: test-2',
            'X-Test: test-3',
            'Set-Cookie: key-1=value-1',
            'Set-Cookie: key-2=value-2',
        ];

        $this->assertSame($code, http_response_code());
        $this->assertSame($expectedHeaders, headers_list());
        $this->assertSame('HTTP/1.1 200 OK', http_response_status_line());
        $this->expectOutputString($contents);
    }

    public function testConstructorThrowExceptionForBufferLengthIsIntegerTypeAndLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SapiEmitter(0);
    }

    public function testEmitDefaultWithBufferLength(): void
    {
        $emitter = new SapiEmitter(8192);
        $response = $this->createResponse();
        $emitter->emit($response);

        $this->assertSame(200, http_response_code());
        $this->assertCount(0, headers_list());
        $this->assertSame([], headers_list());
        $this->assertSame('HTTP/1.1 200 OK', http_response_status_line());
        $this->expectOutputString('');
    }

    public function testEmitWithBufferLengthAndSpecifyArguments(): void
    {
        $emitter = new SapiEmitter(2);
        $response = $this->createResponse($code = 404, ['X-Test' => 'test'], $contents = 'Page not found', '2');
        $emitter->emit($response);

        $this->assertSame($code, http_response_code());
        $this->assertCount(1, headers_list());
        $this->assertSame(['X-Test: test'], headers_list());
        $this->assertSame('HTTP/2 404 Not Found', http_response_status_line());
        $this->expectOutputString($contents);
    }

    public function testEmitWithBufferLengthAndContentRangeHeader(): void
    {
        $emitter = new SapiEmitter(1);
        $response = $this->createResponse($code = 200, ['Content-Range' => 'bytes 0-3/8'], 'Contents');
        $emitter->emit($response);

        $this->assertSame($code, http_response_code());
        $this->assertCount(1, headers_list());
        $this->assertSame(['Content-Range: bytes 0-3/8'], headers_list());
        $this->assertSame('HTTP/1.1 200 OK', http_response_status_line());
        $this->expectOutputString('Cont');
    }

    public function testEmitWithoutBodyTrue(): void
    {
        $emitter = new SapiEmitter();
        $response = $this->createResponse($code = 404, ['X-Test' => 'test'], 'Page not found', '2');
        $emitter->emit($response, true);

        $this->assertSame($code, http_response_code());
        $this->assertCount(1, headers_list());
        $this->assertSame(['X-Test: test'], headers_list());
        $this->assertSame('HTTP/2 404 Not Found', http_response_status_line());
        $this->expectOutputString('');
    }

    public function testEmitWithoutBodyTrueAndWithBufferLengthAndContentRangeHeader(): void
    {
        $emitter = new SapiEmitter(1);
        $response = $this->createResponse($code = 200, ['Content-Range' => 'bytes 0-3/8'], 'Contents');
        $emitter->emit($response, true);

        $this->assertSame($code, http_response_code());
        $this->assertCount(1, headers_list());
        $this->assertSame(['Content-Range: bytes 0-3/8'], headers_list());
        $this->assertSame('HTTP/1.1 200 OK', http_response_status_line());
        $this->expectOutputString('');
    }

    public function testEmitBodyWithNotReadableStream(): void
    {
        $response = new Response(200, [], fopen('php://output', 'c'));
        $this->assertSame('php://output', $response->getBody()->getMetadata('uri'));
        $this->assertFalse($response->getBody()->isReadable());

        $emitter = new SapiEmitter();
        $emitter->emit($response);
        $this->expectOutputString('');

        $emitter = new SapiEmitter(8192);
        $emitter->emit($response);
        $this->expectOutputString('');

        $emitter = new SapiEmitter(8192);
        $emitter->emit($response->withHeader('Content-Range', 'bytes 0-3/8'));
        $this->assertSame(['Content-Range: bytes 0-3/8'], headers_list());
        $this->expectOutputString('');
    }

    public function testEmitThrowOutputAlreadySentException(): void
    {
        $response = new Response(200, [], fopen('php://output', 'c'));
        $response->getBody()->write('Contents');
        $this->expectOutputString('Contents');

        $this->expectException(OutputAlreadySentException::class);
        $this->expectExceptionMessage('Unable to emit response; output has been emitted previously.');

        (new SapiEmitter())->emit($response);
    }

    public function testEmitThrowHeadersAlreadySentException(): void
    {
        MockData::$isHeadersSent = true;

        $this->expectException(HeadersAlreadySentException::class);
        $this->expectExceptionMessage('Unable to emit response; headers already sent.');

        (new SapiEmitter())->emit($this->createResponse());
    }

    /**
     * @return array[]
     */
    public function emitBodyProvider(): array
    {
        return [
            ['Contents', ['Contents'], null, null, null],
            ['Contents', ['Contents'], 8192, null, null],
            ['Contents', ['C', 'o', 'n', 't', 'e', 'n', 't', 's'], 1, null, null],
            ['Contents', ['Co', 'nt', 'en', 'ts'], 2, null, null],
            ['Contents', ['Con', 'ten', 'ts'], 3, null, null],
            ['Contents', ['Content', 's'], 7, null, null],
            ['Contents', ['Contents'], 8192, 0, 8],
            ['Contents', ['Con'], 8192, 0, 2],
            ['Contents', ['Content', 's'], 7, 0, 7],
            ['Contents', ['C', 'o', 'n', 't', 'e', 'n', 't', 's'], 1, 0, 8],
            ['Contents', ['Co', 'nt'], 2, 0, 3],
            ['Contents', ['nte', 'nt'], 3, 2, 6],
            ['Contents', ['ts'], 2, 6, 8],
        ];
    }

    /**
     * @dataProvider emitBodyProvider
     * @param string $contents
     * @param array $expected
     * @param int|null $buffer
     * @param int|null $first
     * @param int|null $last
     */
    public function testEmitBody(string $contents, array $expected, ?int $buffer, ?int $first, ?int $last): void
    {
        $isContentRange = (is_int($first) && is_int($last));
        $outputString = $isContentRange ? implode('', $expected) : $contents;
        $headers = $isContentRange ? ['Content-Range' => "bytes $first-$last/*"] : [];
        $expectedHeaders = $isContentRange ? ["Content-Range: bytes $first-$last/*"] : [];

        $response = $this->createResponse(200, $headers, $contents);
        $emitter = $this->createEmitterMock($response, $buffer);
        $emitter->emit($response);

        $this->assertSame($expectedHeaders, headers_list());
        $this->assertSame($expected, array_filter(MockData::$contentSplitByBytes));
        $this->expectOutputString($outputString);
    }

    /**
     * @param int $statusCode
     * @param array $headers
     * @param string $contents
     * @param string $protocol
     * @return Response
     */
    private function createResponse(
        int $statusCode = 200,
        array $headers = [],
        string $contents = '',
        string $protocol = '1.1'
    ): ResponseInterface {
        $response = new Response($statusCode, $headers, 'php://temp', $protocol);
        $response->getBody()->write($contents);
        return $response;
    }

    /**
     * @param ResponseInterface $response
     * @param int|null $bufferLength
     * @return EmitterInterface
     */
    private function createEmitterMock(ResponseInterface $response, int $bufferLength = null): EmitterInterface
    {
        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->method('emit')->willReturnCallback(function () use ($response, $bufferLength) {
            $body = $response->getBody();

            if ($bufferLength === null) {
                $contents = $body->__toString();
                MockData::$contentSplitByBytes[] = $contents;
                echo $contents;
                return;
            }

            if ($contentRange = $response->getHeaderLine('content-range')) {
                header("Content-Range: $contentRange");
                $first = (int) preg_replace('/^bytes\s(\d)-\d\/\*$/', '$1', $contentRange);
                $last = (int) preg_replace('/^bytes\s\d-(\d)\/\*$/', '$1', $contentRange);
                $length = $last - $first + 1;

                if ($body->isSeekable()) {
                    $body->seek($first);
                }

                while ($length >= $bufferLength && !$body->eof()) {
                    $contents = $body->read($bufferLength);
                    $length -= strlen($contents);
                    MockData::$contentSplitByBytes[] = $contents;
                    echo $contents;
                }

                if ($length > 0 && !$body->eof()) {
                    $contents = $body->read($length);
                    MockData::$contentSplitByBytes[] = $contents;
                    echo $contents;
                }

                return;
            }

            if ($body->isSeekable()) {
                $body->rewind();
            }

            while (!$body->eof()) {
                $contents = $body->read($bufferLength);
                MockData::$contentSplitByBytes[] = $contents;
                echo $contents;
            }
        });

        return $emitter;
    }
}
