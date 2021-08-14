<?php

declare(strict_types=1);

namespace HttpSoft\Emitter;

use HttpSoft\Tests\Emitter\TestAsset\MockData;

use function array_key_exists;
use function explode;
use function function_exists;
use function is_int;
use function is_string;
use function strtolower;
use function strpos;

if (!function_exists(__NAMESPACE__ . '\\header')) {
    /**
     * Mock for the `header()` function.
     *
     * @param string $string
     * @param bool $replace
     * @param int|null $http_response_code
     */
    function header(string $string, bool $replace = true, int $http_response_code = null): void
    {
        if (strpos($string, 'HTTP/') === 0) {
            MockData::$statusLine = $string;

            if (is_int($http_response_code)) {
                MockData::$statusCode = $http_response_code;
            }

            return;
        }

        $headerName = strtolower(explode(':', $string, 2)[0]);

        if ($replace || !array_key_exists($headerName, MockData::$headers)) {
            MockData::$headers[$headerName] = [];
        }

        MockData::$headers[$headerName][] = $string;
    }
}

if (!function_exists(__NAMESPACE__ . '\\header_remove')) {
    /**
     * Mock for the `header_remove()` function.
     *
     * @param string|null $header
     */
    function header_remove(string $header = null): void
    {
        if (is_string($header)) {
            unset(MockData::$headers[strtolower($header)]);
            return;
        }

        MockData::$headers = [];
    }
}

if (!function_exists(__NAMESPACE__ . '\\headers_sent')) {
    /**
     * Mock for the `headers_sent()` function.
     *
     * @return bool
     */
    function headers_sent(): bool
    {
        return MockData::$isHeadersSent;
    }
}

if (!function_exists(__NAMESPACE__ . '\\headers_list')) {
    /**
     * Mock for the `header_list()` function.
     *
     * @return array
     */
    function headers_list(): array
    {
        $list = [];

        foreach (MockData::$headers as $values) {
            foreach ($values as $header) {
                $list[] = $header;
            }
        }

        return $list;
    }
}

if (!function_exists(__NAMESPACE__ . '\\http_response_code')) {
    /**
     * Mock for the `http_response_code()` function.
     *
     * @param int|null $response_code
     * @return int
     */
    function http_response_code(int $response_code = null): int
    {
        if (is_int($response_code)) {
            MockData::$statusCode = $response_code;
        }

        return MockData::$statusCode;
    }
}

if (!function_exists(__NAMESPACE__ . '\\http_response_status_line')) {
    /**
     * Gets or Sets the HTTP response status line.
     *
     * @param string|null $response_status_line
     * @return string
     */
    function http_response_status_line(string $response_status_line = null): string
    {
        if (is_string($response_status_line)) {
            MockData::$statusLine = $response_status_line;
        }

        return MockData::$statusLine;
    }
}
