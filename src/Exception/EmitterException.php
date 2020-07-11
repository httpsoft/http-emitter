<?php

declare(strict_types=1);

namespace HttpSoft\Runner\Exception;

use RuntimeException;

use function sprintf;

class EmitterException extends RuntimeException
{
    /**
     * @param int $bufferLength
     * @return static
     */
    public static function forInvalidBufferLength(int $bufferLength): self
    {
        return new self(sprintf('Buffer length must be greater than zero; received `%d`.', $bufferLength));
    }

    /**
     * @return static
     */
    public static function forHeadersOrOutputSent(): self
    {
        return new self('Unable to emit response. Headers already sent or output has been emitted previously.');
    }
}
