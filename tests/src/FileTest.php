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

use Derafu\File\Directory;
use Derafu\File\Exception\FileAccessException;
use Derafu\File\Exception\FileException;
use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\Exception\PathException;
use Derafu\File\File;
use Derafu\File\Path;
use Derafu\TestsFile\Fixture\UsesTemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
#[UsesClass(Path::class)]
#[UsesClass(Directory::class)]
final class FileTest extends TestCase
{
    use UsesTemporaryDirectory;

    public function testTheContentOfAFileIsRead(): void
    {
        file_put_contents($this->tempDir . '/file.txt', "line 1\nñ\0binary");
        file_put_contents($this->tempDir . '/empty.txt', '');

        $this->assertSame("line 1\nñ\0binary", File::read($this->tempDir . '/file.txt'));
        $this->assertSame('', File::read($this->tempDir . '/empty.txt'));
    }

    public function testAFileThroughASymbolicLinkIsRead(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'content');
        symlink($this->tempDir . '/file.txt', $this->tempDir . '/link.txt');

        $this->assertSame('content', File::read($this->tempDir . '/link.txt'));
    }

    public function testAFileThatDoesNotExistCanNotBeRead(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The file does not exist: ' . $this->tempDir . '/missing.txt');

        File::read($this->tempDir . '/missing.txt');
    }

    public function testABrokenSymbolicLinkIsAFileThatDoesNotExist(): void
    {
        symlink($this->tempDir . '/missing.txt', $this->tempDir . '/broken.txt');

        $this->expectException(FileNotFoundException::class);

        File::read($this->tempDir . '/broken.txt');
    }

    public function testADirectoryCanNotBeRead(): void
    {
        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('The path is not a file: ' . $this->tempDir);

        File::read($this->tempDir);
    }

    public function testAFileWithoutPermissionCanNotBeRead(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read files without permission.');
        }
        file_put_contents($this->tempDir . '/secret.txt', 'x');
        chmod($this->tempDir . '/secret.txt', 0000);

        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('Cannot read file: ' . $this->tempDir . '/secret.txt');

        File::read($this->tempDir . '/secret.txt');
    }

    public function testAPathWithANullCharacterCanNotBeRead(): void
    {
        $this->expectException(PathException::class);

        File::read("a\0b");
    }

    public function testTheContentOfAFileIsWritten(): void
    {
        File::write($this->tempDir . '/file.txt', 'content');

        $this->assertSame('content', file_get_contents($this->tempDir . '/file.txt'));
        $this->assertSame(0666 & ~umask(), fileperms($this->tempDir . '/file.txt') & 0777);
    }

    public function testTheContentOfAFileThatExistsIsReplacedWithoutLeavingTemporaryFiles(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'old');

        File::write($this->tempDir . '/file.txt', 'new');

        $this->assertSame('new', file_get_contents($this->tempDir . '/file.txt'));
        $this->assertSame([$this->tempDir . '/file.txt'], glob($this->tempDir . '/*'));
    }

    public function testTheMissingDirectoriesAreCreatedWhenWriting(): void
    {
        File::write($this->tempDir . '/a/b/file.txt', 'content');

        $this->assertSame('content', file_get_contents($this->tempDir . '/a/b/file.txt'));
    }

    public function testThePermissionsThatAreGivenAreSet(): void
    {
        File::write($this->tempDir . '/file.txt', 'content', 0640);

        $this->assertSame(0640, fileperms($this->tempDir . '/file.txt') & 0777);
    }

    public function testAFileCanNotBeWrittenWhereTheDirectoryCanNotBeCreated(): void
    {
        file_put_contents($this->tempDir . '/is-a-file', 'x');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to create directory (' . $this->tempDir . '/is-a-file/sub).');

        File::write($this->tempDir . '/is-a-file/sub/file.txt', 'content');
    }

    public function testAFileCanNotBeWrittenInADirectoryThatIsNotWritable(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can write files without permission.');
        }
        mkdir($this->tempDir . '/read-only');
        chmod($this->tempDir . '/read-only', 0555);

        try {
            File::write($this->tempDir . '/read-only/file.txt', 'content');
            $this->fail('The directory is not writable.');
        } catch (FileAccessException $e) {
            $this->assertSame('The directory is not writable: ' . $this->tempDir . '/read-only', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->tempDir . '/read-only/file.txt');
    }

    public function testNoTemporaryFileIsLeftWhenTheFileCanNotBeMoved(): void
    {
        // A file can not be moved over a directory.
        mkdir($this->tempDir . '/target');

        try {
            File::write($this->tempDir . '/target', 'content');
            $this->fail('A file can not replace a directory.');
        } catch (FileException $e) {
            $this->assertSame('Unable to move temporary file to target location (' . $this->tempDir . '/target).', $e->getMessage());
        }

        $this->assertSame([$this->tempDir . '/target'], glob($this->tempDir . '/*'));
    }

    public function testAPathWithANullCharacterCanNotBeWritten(): void
    {
        $this->expectException(PathException::class);

        File::write("a\0b", 'content');
    }

    public function testAFileIsDeleted(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'x');

        File::delete($this->tempDir . '/file.txt');

        $this->assertFileDoesNotExist($this->tempDir . '/file.txt');
    }

    public function testWhatDoesNotExistIsAlreadyDeleted(): void
    {
        File::delete($this->tempDir . '/missing.txt');

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testASymbolicLinkIsDeletedAndNotWhatItPointsTo(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'keep');
        symlink($this->tempDir . '/file.txt', $this->tempDir . '/link.txt');
        symlink($this->tempDir . '/missing.txt', $this->tempDir . '/broken.txt');

        File::delete($this->tempDir . '/link.txt');
        File::delete($this->tempDir . '/broken.txt');

        $this->assertFalse(is_link($this->tempDir . '/link.txt'));
        $this->assertFalse(is_link($this->tempDir . '/broken.txt'));
        $this->assertSame('keep', file_get_contents($this->tempDir . '/file.txt'));
    }

    public function testADirectoryIsNotDeletedAsAFile(): void
    {
        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('The path is a directory, not a file: ' . $this->tempDir);

        File::delete($this->tempDir);
    }

    public function testAFileCanNotBeDeletedFromADirectoryThatIsNotWritable(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can delete files without permission.');
        }
        mkdir($this->tempDir . '/locked');
        file_put_contents($this->tempDir . '/locked/file.txt', 'x');
        chmod($this->tempDir . '/locked', 0555);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to delete file: ' . $this->tempDir . '/locked/file.txt');

        File::delete($this->tempDir . '/locked/file.txt');
    }

    public function testAPathWithANullCharacterCanNotBeDeleted(): void
    {
        $this->expectException(PathException::class);

        File::delete("a\0b");
    }
}
