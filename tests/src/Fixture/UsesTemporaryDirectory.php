<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsFile\Fixture;

/**
 * Gives a test a temporary directory of its own, which is removed afterwards
 * (also when a test left a directory without permissions).
 */
trait UsesTemporaryDirectory
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/derafu-file-' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->restorePermissions($this->tempDir);
        $this->removeTree($this->tempDir);
    }

    private function restorePermissions(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        chmod($directory, 0755);
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->restorePermissions($directory . '/' . $entry);
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        rmdir($path);
    }

    /**
     * Whether the user that runs the tests ignores permissions (as root does).
     */
    private function ignoresPermissions(): bool
    {
        $probe = $this->tempDir . '/permission-probe';
        mkdir($probe);
        chmod($probe, 0555);
        $ignores = is_writable($probe);
        chmod($probe, 0755);
        rmdir($probe);

        return $ignores;
    }

    private function fixture(string $path): string
    {
        return dirname(__DIR__, 2) . '/fixtures/' . $path;
    }
}
