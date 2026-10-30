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

use Derafu\File\Exception\FileAccessException;
use Derafu\File\Exception\FileException;
use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\Exception\PathException;
use Throwable;

/**
 * Reads, writes and deletes a file. A failure is an exception that names the
 * file, never `false` and never a warning.
 */
final class File
{
    /**
     * Reads the whole content of a file.
     *
     * @param string $path The file.
     * @return string The content.
     * @throws PathException If the path has a null character.
     * @throws FileNotFoundException If the file does not exist.
     * @throws FileAccessException If the path is not a file or it can not be read.
     * @throws FileException If the reading fails.
     */
    public static function read(string $path): string
    {
        if (!file_exists($path)) {
            Path::assertValid($path);
            throw new FileNotFoundException(['The file does not exist: {file}', 'file' => $path]);
        }

        if (!is_file($path)) {
            throw new FileAccessException(['The path is not a file: {file}', 'file' => $path]);
        }

        if (!is_readable($path)) {
            throw new FileAccessException(['Cannot read file: {file}', 'file' => $path]);
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new FileException(['Failed to read file: {file}', 'file' => $path]);
        }

        return $content;
    }

    /**
     * Writes the content of a file, replacing the one that it had.
     *
     * The write is atomic:
     *
     *   - The content goes to a temporary file in the same directory, which is
     *     then moved over the target. Whoever reads the file sees the old
     *     content or the new one, never a part of it.
     *   - If something fails, the original file stays as it was and the
     *     temporary file is deleted.
     *   - The directories that are missing are created.
     *
     * @param string $path The file.
     * @param string $content The content.
     * @param int|null $permissions The permissions of the file. By default they
     * are `0666` less the `umask` of the process.
     * @throws PathException If the path has a null character.
     * @throws FileAccessException If the directory can not be written.
     * @throws FileException If the directory, the temporary file or the move
     * fail.
     */
    public static function write(string $path, string $content, ?int $permissions = null): void
    {
        Path::assertValid($path);

        $directory = dirname($path);
        Directory::ensure($directory);

        // Without it PHP would put the temporary file in another directory and
        // warn, and the move to this one would fail anyway.
        if (!is_writable($directory)) {
            throw new FileAccessException(['The directory is not writable: {directory}', 'directory' => $directory]);
        }

        $temporary = tempnam($directory, basename($path));
        if ($temporary === false) {
            throw new FileException(['Unable to create temporary file in directory ({directory}).', 'directory' => $directory]);
        }

        try {
            if (@file_put_contents($temporary, $content) === false) {
                throw new FileException(['Unable to write content to temporary file ({file}).', 'file' => $temporary]);
            }

            if ($permissions === null) {
                @chmod($temporary, 0666 & ~umask());
            } elseif (!@chmod($temporary, $permissions)) {
                throw new FileException(['Unable to set permissions on temporary file ({file}).', 'file' => $temporary]);
            }

            if (!@rename($temporary, $path)) {
                throw new FileException(['Unable to move temporary file to target location ({file}).', 'file' => $path]);
            }
        } catch (Throwable $e) {
            @unlink($temporary);
            throw $e;
        }
    }

    /**
     * Deletes a file, or a symbolic link, without following it.
     *
     * A path that does not exist is already deleted: nothing happens.
     *
     * @param string $path The file.
     * @throws FileAccessException If the path is a directory.
     * @throws FileException If the deletion fails.
     */
    public static function delete(string $path): void
    {
        if (!is_link($path) && !file_exists($path)) {
            Path::assertValid($path);

            return;
        }

        if (!is_link($path) && is_dir($path)) {
            throw new FileAccessException(['The path is a directory, not a file: {file}', 'file' => $path]);
        }

        if (!@unlink($path)) {
            throw new FileException(['Failed to delete file: {file}', 'file' => $path]);
        }
    }
}
