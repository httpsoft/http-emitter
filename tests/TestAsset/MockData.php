<?php

declare(strict_types=1);

namespace HttpSoft\Tests\Emitter\TestAsset;

class MockData
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

    /**
     * @var bool
     */
    public static bool $isHeadersSent = false;

    /**
     * @var array
     */
    public static array $contentSplitByBytes = [];

    /**
     * Reset data.
     */
    public static function reset(): void
    {
        self::$headers = [];
        self::$statusCode = 200;
        self::$statusLine = '';
        self::$isHeadersSent = false;
        self::$contentSplitByBytes = [];
    }
}
