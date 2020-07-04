<?php

declare(strict_types=1);

namespace HttpSoft\Tests\Runner\TestAssert;

class SapiResponseData
{
    /**
     * @var string[][]
     */
    public static array $headers = [];

    /**
     * @var int
     */
    public static int $statusCode = 200;

    /**
     * @var string
     */
    public static string $statusLine = '';
}
