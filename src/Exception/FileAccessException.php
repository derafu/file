<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\File\Exception;

/**
 * A file or a directory exists, but it can not be used as it was asked: it is
 * not of the expected kind, or it can not be read or written.
 */
class FileAccessException extends FileException
{
}
