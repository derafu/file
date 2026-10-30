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
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Creates, lists and removes directories. A failure is an exception that names
 * the directory, never `false` and never a warning.
 *
 * A symbolic link is never followed: a link to a directory is not entered when
 * listing, and is removed as a link when removing.
 */
final class Directory
{
    /**
     * Makes sure that a directory exists, creating the ones that are missing.
     *
     * @param string $path The directory.
     * @param int $mode The permissions of the directories that are created,
     * before the `umask` of the process.
     * @throws PathException If the path has a null character.
     * @throws FileException If the directory can not be created, or the path is
     * something else than a directory.
     */
    public static function ensure(string $path, int $mode = 0777): void
    {
        Path::assertValid($path);

        if (is_dir($path)) {
            return;
        }

        // Another process can create it between the check and the creation.
        if (!@mkdir($path, $mode, true) && !is_dir($path)) {
            throw new FileException(['Unable to create directory ({directory}).', 'directory' => $path]);
        }
    }

    /**
     * Lists the files that a directory has, in the order of their paths.
     *
     * Only files are listed (not directories), with their hidden ones, and each
     * path starts with the directory as it was given. The files that a
     * symbolic link to a directory contains are not listed.
     *
     * @param string $path The directory.
     * @param list<string> $extensions The extensions of the files that are
     * listed, with or without the point and in upper or lower case. By default
     * every file is listed.
     * @param bool $recursive Whether the directories inside are also listed.
     * @return list<string> The paths of the files.
     * @throws PathException If the path has a null character.
     * @throws FileNotFoundException If the directory does not exist.
     * @throws FileAccessException If the path is not a directory or it can not
     * be read.
     * @throws FileException If the reading fails.
     */
    public static function files(string $path, array $extensions = [], bool $recursive = true): array
    {
        if (!file_exists($path)) {
            Path::assertValid($path);
            throw new FileNotFoundException(['The directory does not exist: {directory}', 'directory' => $path]);
        }

        if (!is_dir($path)) {
            throw new FileAccessException(['The path is not a directory: {directory}', 'directory' => $path]);
        }

        if (!is_readable($path)) {
            throw new FileAccessException(['Cannot read directory: {directory}', 'directory' => $path]);
        }

        $extensions = array_map(static fn (string $extension): string => strtolower(ltrim($extension, '.')), $extensions);
        $root = rtrim($path, '/\\');

        $files = [];
        try {
            $directory = new RecursiveDirectoryIterator($root === '' ? '/' : $root, FilesystemIterator::SKIP_DOTS);
            /** @var iterable<SplFileInfo> $items */
            $items = $recursive ? new RecursiveIteratorIterator($directory) : $directory;
            foreach ($items as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                if ($extensions !== [] && !in_array(strtolower($item->getExtension()), $extensions, true)) {
                    continue;
                }
                $files[] = $item->getPathname();
            }
        } catch (UnexpectedValueException $e) {
            throw new FileException([
                'Failed to read directory {directory}: {error}',
                'directory' => $path,
                'error' => $e->getMessage(),
            ], 0, $e);
        }

        sort($files);

        return $files;
    }

    /**
     * Removes a directory with everything that it has inside.
     *
     * A symbolic link inside is removed, never followed: what it points to
     * stays. A directory that does not exist is already removed: nothing
     * happens.
     *
     * @param string $path The directory.
     * @throws PathException If the path has a null character.
     * @throws FileAccessException If the path is a file.
     * @throws FileException If something can not be removed.
     */
    public static function remove(string $path): void
    {
        if (!is_link($path) && !file_exists($path)) {
            Path::assertValid($path);

            return;
        }

        if (!is_link($path) && !is_dir($path)) {
            throw new FileAccessException(['The path is not a directory: {directory}', 'directory' => $path]);
        }

        try {
            if (!is_link($path)) {
                /** @var iterable<SplFileInfo> $items */
                $items = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($items as $item) {
                    $removed = $item->isLink() || !$item->isDir()
                        ? @unlink($item->getPathname())
                        : @rmdir($item->getPathname());
                    if (!$removed) {
                        throw self::removalFailure($item->getPathname());
                    }
                }
            }

            $removed = is_link($path) ? @unlink($path) : @rmdir($path);
            if (!$removed) {
                throw self::removalFailure($path);
            }
        } catch (UnexpectedValueException $e) {
            throw new FileException([
                'Failed to remove directory {directory}: {error}',
                'directory' => $path,
                'error' => $e->getMessage(),
            ], 0, $e);
        }
    }

    /**
     * Creates the error of something that could not be removed.
     */
    private static function removalFailure(string $path): FileException
    {
        return new FileException([
            'Failed to remove directory {directory}: {error}',
            'directory' => $path,
            'error' => error_get_last()['message'] ?? '',
        ]);
    }
}
