<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, MIME types and ZIP archives.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsFile\Fixture;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A PSR-7 stream over a string, or over a size that is made while it is read
 * (a stream that is larger than the memory, as a file that is not on the disk).
 */
final class TextStream implements StreamInterface
{
    private int $position = 0;

    private function __construct(private readonly ?string $text, private readonly int $size)
    {
    }

    public static function of(string $text): self
    {
        return new self($text, strlen($text));
    }

    /**
     * A stream of `$size` bytes that are made as they are read.
     */
    public static function generated(int $size): self
    {
        return new self(null, $size);
    }

    public function __toString(): string
    {
        throw new RuntimeException('A stream must not be read whole.');
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= $this->size;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Not seekable.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('Not seekable.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Read-only.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $length = min($length, $this->size - $this->position);
        if ($length <= 0) {
            return '';
        }

        $chunk = $this->text === null
            ? str_repeat('0123456789abcdef', intdiv($length, 16) + 1)
            : substr($this->text, $this->position, $length);
        $this->position += $length;

        return substr($chunk, 0, $length);
    }

    public function getContents(): string
    {
        throw new RuntimeException('A stream must not be read whole.');
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
