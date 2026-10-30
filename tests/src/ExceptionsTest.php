<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsFile;

use Derafu\File\Exception\FileAccessException;
use Derafu\File\Exception\FileException;
use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\Exception\PathException;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileException::class)]
#[CoversClass(FileNotFoundException::class)]
#[CoversClass(FileAccessException::class)]
#[CoversClass(PathException::class)]
final class ExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{class-string<FileException>}>
     */
    public static function exceptions(): array
    {
        return [
            'file' => [FileException::class],
            'not found' => [FileNotFoundException::class],
            'access' => [FileAccessException::class],
            'path' => [PathException::class],
        ];
    }

    /**
     * @param class-string<FileException> $class
     */
    #[DataProvider('exceptions')]
    public function testEveryErrorIsAFileErrorWithATranslatableMessage(string $class): void
    {
        $exception = new $class(['The file does not exist: {file}', 'file' => 'a.txt']);

        $this->assertInstanceOf(FileException::class, $exception);
        $this->assertInstanceOf(TranslatableRuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The file does not exist: a.txt', $exception->getMessage());
    }
}
