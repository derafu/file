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

use finfo;
use Symfony\Component\Mime\MimeTypes;

/**
 * Tells the MIME type of a file or of a content by what it has inside, and the
 * MIME types that an extension can have. What can not be told is `null`, not
 * `false`.
 */
final class Mime
{
    /**
     * Gets the MIME type of a file.
     *
     * The type is detected by the content of the file, not by its name. A
     * symbolic link is followed.
     *
     * @param string $path The file.
     * @return string|null The MIME type, or `null` if the path is not a regular
     * file that can be read (it does not exist, it is a directory, or it has no
     * permission to be read), or if the type can not be determined. An empty
     * file is `application/x-empty`.
     */
    public static function fromFile(string $path): ?string
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        return (new MimeTypes())->guessMimeType($path) ?: null;
    }

    /**
     * Gets the MIME type of a content that is in memory, as `fromFile()` does
     * with a file.
     *
     * Only the content that is given is read, so it can be just the start of a
     * file. That is enough for the formats that have a signature at the start
     * (images, PDF, ZIP), but not for those that need the end of the file to be
     * known (a `.docx` is detected as `application/zip` with only a start).
     *
     * @param string $content The content, or the start of it.
     * @return string|null The MIME type, or `null` if it can not be
     * determined. An empty content is `application/x-empty`.
     */
    public static function fromContent(string $content): ?string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: null;
    }

    /**
     * Gets the MIME types that a file extension can have.
     *
     * An extension can have more than one type, as its aliases (`jpg` is
     * `image/jpeg` and `image/pjpeg`).
     *
     * @param string $extension The extension, with or without the leading point
     * and in upper or lower case (`csv`, `.csv` and `CSV` are the same).
     * @return list<string> The types of the extension, or an empty list if the
     * extension is empty or is not known.
     */
    public static function typesOfExtension(string $extension): array
    {
        if (str_starts_with($extension, '.')) {
            $extension = substr($extension, 1);
        }

        if ($extension === '') {
            return [];
        }

        return array_values((new MimeTypes())->getMimeTypes($extension));
    }
}
