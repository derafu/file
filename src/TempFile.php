<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\File;

use Derafu\File\Exception\FileException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Gives a temporary file to a piece of code and deletes it afterwards, even if
 * the code fails, so no file is left in the temporary directory.
 */
final class TempFile
{
    /**
     * Creates a temporary file with a content, runs a callback with its path,
     * deletes the file and returns what the callback returned.
     *
     * The file is in the temporary directory of the system, with a name that no
     * other file has. It is deleted when the callback returns and also when it
     * throws, and then the exception of the callback is the one that goes up.
     *
     * @template T
     * @param string $content The content of the file. It can be empty, for a
     * callback that writes the file.
     * @param callable(string): T $callback It receives the path of the file.
     * @param string $extension The extension of the file, with or without the
     * point. It can have more than one (`xml.gz`).
     * @param string $prefix The start of the name of the file.
     * @return T What the callback returned.
     * @throws InvalidArgumentException If the extension or the prefix have
     * something else than letters, numbers, points, hyphens and underscores.
     * @throws FileException If the file can not be created, named or written.
     */
    public static function with(
        string $content,
        callable $callback,
        string $extension = '',
        string $prefix = 'derafu_'
    ): mixed {
        if (str_starts_with($extension, '.')) {
            $extension = substr($extension, 1);
        }
        if ($extension !== '' && preg_match('/^[A-Za-z0-9]+(\.[A-Za-z0-9]+)*$/', $extension) !== 1) {
            throw new InvalidArgumentException(['The extension "{extension}" is not valid.', 'extension' => $extension]);
        }
        if (preg_match('/^[A-Za-z0-9._-]*$/', $prefix) !== 1) {
            throw new InvalidArgumentException(['The prefix "{prefix}" is not valid.', 'prefix' => $prefix]);
        }

        $directory = sys_get_temp_dir();
        $path = tempnam($directory, $prefix);
        if ($path === false) {
            throw new FileException(['Unable to create temporary file in directory ({directory}).', 'directory' => $directory]);
        }

        try {
            if ($extension !== '') {
                if (!@rename($path, $path . '.' . $extension)) {
                    throw new FileException(['Unable to move temporary file to target location ({file}).', 'file' => $path . '.' . $extension]);
                }
                $path .= '.' . $extension;
            }

            if ($content !== '' && @file_put_contents($path, $content) === false) {
                throw new FileException(['Unable to write content to temporary file ({file}).', 'file' => $path]);
            }

            return $callback($path);
        } finally {
            // It does not use File::delete(): its error would hide the one of
            // the callback.
            @unlink($path);
        }
    }
}
