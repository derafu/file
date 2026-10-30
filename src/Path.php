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

use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\Exception\PathException;

/**
 * Works with the paths of files and directories, without touching their
 * content. The paths that do not exist or that leave a directory are errors,
 * not `false`.
 */
final class Path
{
    /**
     * Says that a path can be given to the functions of the file system.
     *
     * PHP stops with a `ValueError` on a path that has a null character, as
     * `unlink()` or `mkdir()` do, instead of saying that the path is not valid.
     *
     * @param string $path The path.
     * @throws PathException If the path has a null character.
     */
    public static function assertValid(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new PathException('The path contains a null character.');
        }
    }

    /**
     * Joins the parts of a path with a single separator between them.
     *
     * The empty parts are left out, the first part keeps its leading
     * separator, and the separators at the end of a part and at the start of
     * the next ones are not repeated. The parts are not resolved: `..` stays.
     *
     * @param string ...$parts The parts of the path.
     * @return string The path, or an empty string if all the parts are empty.
     */
    public static function join(string ...$parts): string
    {
        $path = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $path = $path === ''
                ? rtrim($part, '/\\') . (trim($part, '/\\') === '' ? '/' : '')
                : rtrim($path, '/\\') . '/' . ltrim($part, '/\\')
            ;
        }

        return $path;
    }

    /**
     * Gives the absolute path of something that exists, with its `.` and `..`
     * resolved and its symbolic links followed.
     *
     * @param string $path The path.
     * @return string The real path.
     * @throws PathException If the path has a null character.
     * @throws FileNotFoundException If the path does not exist.
     */
    public static function resolve(string $path): string
    {
        self::assertValid($path);

        // The real path of an empty path is the current directory.
        $resolved = $path === '' ? false : realpath($path);
        if ($resolved === false) {
            throw new FileNotFoundException(['The path does not exist: {path}', 'path' => $path]);
        }

        return $resolved;
    }

    /**
     * Gives the absolute path of something that is inside a directory, and
     * refuses what is outside of it.
     *
     * The relative path is taken from the directory even if it starts with a
     * separator. Its `..` and the symbolic links that it goes through are
     * resolved before checking, so neither can take it out of the directory.
     * It is the check that a path that comes from a request needs before the
     * file is read.
     *
     * @param string $base The directory that contains the path. It must exist.
     * @param string $relative The path inside the directory.
     * @return string The real path, which is the real path of the directory
     * itself when the relative path is empty or `.`.
     * @throws PathException If a path has a null character, or the real path is
     * outside of the directory.
     * @throws FileNotFoundException If the directory or the path do not exist.
     */
    public static function resolveWithin(string $base, string $relative): string
    {
        $root = self::resolve($base);
        self::assertValid($relative);

        $resolved = realpath($root . DIRECTORY_SEPARATOR . ltrim($relative, '/\\'));
        if ($resolved === false) {
            throw new FileNotFoundException(['The path does not exist: {path}', 'path' => $relative]);
        }

        if ($resolved !== $root && !str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new PathException([
                'The path {path} is outside of {base}.',
                'path' => $relative,
                'base' => $base,
            ]);
        }

        return $resolved;
    }

    /**
     * Gives the extension of a path, without the point and as it is written.
     *
     * @param string $path The path.
     * @return string The extension, or an empty string if it has none.
     */
    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Gives the name of the file of a path, without its directory and without
     * its last extension.
     *
     * @param string $path The path.
     * @return string The name.
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
