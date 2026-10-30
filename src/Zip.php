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
use Derafu\File\Exception\FileNotFoundException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;
use ZipStream\ZipStream;

/**
 * Creates and extracts ZIP archives. A failure is an exception that says what
 * failed, never `false` and never a warning.
 */
final class Zip
{
    /**
     * Creates a ZIP archive from contents that are in memory, with no
     * temporary file.
     *
     * @param array<array-key, mixed> $files The files of the archive: the name
     * that each one has inside the archive (it can have directories, as
     * `reports/a.xml`) and its content, that must be a string. They go in the
     * order of the array.
     * @return string The archive.
     * @throws InvalidArgumentException If a name is empty, has a null
     * character or a backslash, starts with a slash or has a `..` part, or a
     * content is not a string.
     * @throws FileException If the archive can not be created, as when a name
     * is too long.
     */
    public static function fromContents(array $files): string
    {
        $entries = [];
        foreach ($files as $name => $content) {
            $name = (string) $name;
            if ($name === '') {
                throw new InvalidArgumentException('The name of a ZIP entry must not be empty.');
            }
            // Whoever extracts the archive would find the file outside of the
            // directory, or with another name (the library changes them).
            if (
                str_contains($name, "\0")
                || str_contains($name, '\\')
                || str_starts_with($name, '/')
                || in_array('..', explode('/', $name), true)
            ) {
                throw new InvalidArgumentException(['The name of the ZIP entry "{name}" is not valid.', 'name' => $name]);
            }
            if (!is_string($content)) {
                throw new InvalidArgumentException([
                    'The content of the ZIP entry "{name}" must be a string, {given} given.',
                    'name' => $name,
                    'given' => get_debug_type($content),
                ]);
            }
            $entries[$name] = $content;
        }

        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw new FileException(['Failed to create ZIP file: {error}', 'error' => 'php://memory']);
        }

        try {
            // The archive goes to the memory: the headers of a download are not
            // sent (they would be the ones of the response of whoever calls).
            $zip = new ZipStream(outputStream: $stream, sendHttpHeaders: false);
            foreach ($entries as $name => $content) {
                $zip->addFile((string) $name, $content);
            }
            $zip->finish();

            rewind($stream);
            $archive = stream_get_contents($stream);
        } catch (Exception $e) {
            throw new FileException(['Failed to create ZIP file: {error}', 'error' => $e->getMessage()], 0, $e);
        } finally {
            fclose($stream);
        }

        if ($archive === false) {
            throw new FileException(['Failed to create ZIP file: {error}', 'error' => 'php://memory']);
        }

        return $archive;
    }

    /**
     * Creates a ZIP archive file from a file or a directory.
     *
     * A directory goes with everything that it has inside, and each entry is
     * named by its path inside the directory as it was given, so a directory
     * that is reached through a symbolic link, or with a trailing slash, gives
     * the same names. A file goes with its name.
     *
     * @param string $source The file or the directory.
     * @param string $destination The archive that is created.
     * @throws FileNotFoundException If the source does not exist.
     * @throws FileException If the archive can not be created. The archive is
     * not left half made.
     */
    public static function fromPath(string $source, string $destination): void
    {
        Path::assertValid($source);
        Path::assertValid($destination);
        if (!file_exists($source)) {
            throw new FileNotFoundException(['The path does not exist: {path}', 'path' => $source]);
        }

        $output = @fopen($destination, 'wb');
        if ($output === false) {
            throw new FileException(['Cannot open destination file for writing: {destination}', 'destination' => $destination]);
        }

        try {
            $zip = new ZipStream(outputStream: $output, sendHttpHeaders: false);
            if (is_dir($source)) {
                $directory = rtrim($source, '/\\');
                /** @var iterable<SplFileInfo> $files */
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        continue;
                    }
                    $zip->addFileFromPath(substr($file->getPathname(), strlen($directory) + 1), $file->getPathname());
                }
            } else {
                $zip->addFileFromPath(basename($source), $source);
            }
            $zip->finish();
        } catch (Exception $e) {
            @unlink($destination);
            throw new FileException(['Failed to create ZIP file: {error}', 'error' => $e->getMessage()], 0, $e);
        } finally {
            fclose($output);
        }
    }

    /**
     * Extracts a ZIP archive into a directory, which is created if it does not
     * exist.
     *
     * @param string $zipFile The archive.
     * @param string $destination The directory where the files go.
     * @param bool $overwrite Whether the files that already exist are
     * replaced. If not, one that exists is an error and nothing is extracted.
     * @throws FileNotFoundException If the archive does not exist.
     * @throws FileException If the extension `zip` is not available, the archive
     * can not be opened, a file already exists, or the extraction fails.
     */
    public static function extract(string $zipFile, string $destination, bool $overwrite = false): void
    {
        if (!extension_loaded('zip')) {
            throw new FileException('ZIP extension is not available');
        }

        Path::assertValid($zipFile);
        Path::assertValid($destination);
        if (!is_file($zipFile)) {
            throw new FileNotFoundException(['The file does not exist: {file}', 'file' => $zipFile]);
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipFile);
        if ($result !== true) {
            throw new FileException([
                'Failed to open ZIP file: {file} (Error code: {code})',
                'file' => $zipFile,
                'code' => $result,
            ]);
        }

        try {
            if (!$overwrite) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    // A directory entry only says that the directory exists.
                    if (str_ends_with($name, '/')) {
                        continue;
                    }
                    $path = $destination . DIRECTORY_SEPARATOR . $name;
                    if (file_exists($path)) {
                        throw new FileException(['File already exists: {file}', 'file' => $path]);
                    }
                }
            }

            if (!@$zip->extractTo($destination)) {
                throw new FileException(['Failed to extract ZIP file to: {destination}', 'destination' => $destination]);
            }
        } finally {
            $zip->close();
        }
    }
}
