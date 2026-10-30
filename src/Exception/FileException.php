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

use Derafu\Translation\Exception\Core\TranslatableRuntimeException;

/**
 * Base of the errors that the package finds when it works with files,
 * directories, paths and archives. Its message is translatable.
 */
class FileException extends TranslatableRuntimeException
{
}
