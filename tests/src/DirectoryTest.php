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
use Derafu\File\Path;
use Derafu\TestsFile\Fixture\UsesTemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Directory::class)]
#[UsesClass(Path::class)]
final class DirectoryTest extends TestCase
{
    use UsesTemporaryDirectory;

    private function tree(): void
    {
        mkdir($this->tempDir . '/tree/sub/deep', 0777, true);
        file_put_contents($this->tempDir . '/tree/b.xml', 'b');
        file_put_contents($this->tempDir . '/tree/a.TXT', 'a');
        file_put_contents($this->tempDir . '/tree/.hidden', 'h');
        file_put_contents($this->tempDir . '/tree/sub/c.txt', 'c');
        file_put_contents($this->tempDir . '/tree/sub/deep/d.xml', 'd');
    }

    public function testMissingDirectoriesAreCreated(): void
    {
        Directory::ensure($this->tempDir . '/a/b/c');

        $this->assertDirectoryExists($this->tempDir . '/a/b/c');
    }

    public function testADirectoryThatExistsIsLeftAsItIs(): void
    {
        mkdir($this->tempDir . '/exists');
        file_put_contents($this->tempDir . '/exists/file.txt', 'x');

        Directory::ensure($this->tempDir . '/exists');

        $this->assertFileExists($this->tempDir . '/exists/file.txt');
    }

    public function testTheModeOfTheNewDirectoriesIsSet(): void
    {
        Directory::ensure($this->tempDir . '/private/inside', 0700);

        $this->assertSame(0700, fileperms($this->tempDir . '/private/inside') & 0777);
    }

    public function testADirectoryCanNotBeCreatedWhereThereIsAFile(): void
    {
        file_put_contents($this->tempDir . '/is-a-file', 'x');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to create directory (' . $this->tempDir . '/is-a-file).');

        Directory::ensure($this->tempDir . '/is-a-file');
    }

    public function testADirectoryWithANullCharacterCanNotBeCreated(): void
    {
        $this->expectException(PathException::class);

        Directory::ensure("a\0b");
    }

    public function testTheFilesAreListedInTheOrderOfTheirPaths(): void
    {
        $this->tree();

        $this->assertSame([
            $this->tempDir . '/tree/.hidden',
            $this->tempDir . '/tree/a.TXT',
            $this->tempDir . '/tree/b.xml',
            $this->tempDir . '/tree/sub/c.txt',
            $this->tempDir . '/tree/sub/deep/d.xml',
        ], Directory::files($this->tempDir . '/tree'));
    }

    public function testOnlyTheFilesOfTheDirectoryAreListedWhenItIsNotRecursive(): void
    {
        $this->tree();

        $this->assertSame([
            $this->tempDir . '/tree/.hidden',
            $this->tempDir . '/tree/a.TXT',
            $this->tempDir . '/tree/b.xml',
        ], Directory::files($this->tempDir . '/tree', recursive: false));
    }

    public function testTheFilesAreFilteredByExtensionWithoutCaringForCaseOrPoint(): void
    {
        $this->tree();

        $this->assertSame([
            $this->tempDir . '/tree/a.TXT',
            $this->tempDir . '/tree/sub/c.txt',
        ], Directory::files($this->tempDir . '/tree', ['txt']));
        $this->assertSame([
            $this->tempDir . '/tree/a.TXT',
            $this->tempDir . '/tree/b.xml',
            $this->tempDir . '/tree/sub/c.txt',
            $this->tempDir . '/tree/sub/deep/d.xml',
        ], Directory::files($this->tempDir . '/tree', ['.XML', 'Txt']));
    }

    public function testTheDirectoryIsListedWithItsPathAsItWasGiven(): void
    {
        $this->tree();

        $files = Directory::files($this->tempDir . '/tree///', ['xml'], recursive: false);

        $this->assertSame([$this->tempDir . '/tree/b.xml'], $files);
    }

    public function testASymbolicLinkToADirectoryIsNotEntered(): void
    {
        mkdir($this->tempDir . '/outside');
        file_put_contents($this->tempDir . '/outside/other.txt', 'x');
        mkdir($this->tempDir . '/tree');
        file_put_contents($this->tempDir . '/tree/real.txt', 'x');
        symlink($this->tempDir . '/outside', $this->tempDir . '/tree/link');
        symlink($this->tempDir . '/tree/real.txt', $this->tempDir . '/tree/file-link.txt');

        $this->assertSame([
            $this->tempDir . '/tree/file-link.txt',
            $this->tempDir . '/tree/real.txt',
        ], Directory::files($this->tempDir . '/tree'));
    }

    public function testADirectoryWithoutFilesGivesAnEmptyList(): void
    {
        mkdir($this->tempDir . '/empty');
        mkdir($this->tempDir . '/empty/only-a-directory');

        $this->assertSame([], Directory::files($this->tempDir . '/empty'));
    }

    public function testADirectoryThatDoesNotExistCanNotBeListed(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The directory does not exist: ' . $this->tempDir . '/missing');

        Directory::files($this->tempDir . '/missing');
    }

    public function testAFileCanNotBeListed(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'x');

        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('The path is not a directory: ' . $this->tempDir . '/file.txt');

        Directory::files($this->tempDir . '/file.txt');
    }

    public function testADirectoryWithoutPermissionCanNotBeListed(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read directories without permission.');
        }
        mkdir($this->tempDir . '/locked');
        chmod($this->tempDir . '/locked', 0000);

        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('Cannot read directory: ' . $this->tempDir . '/locked');

        Directory::files($this->tempDir . '/locked');
    }

    public function testADirectoryInsideWithoutPermissionStopsTheListing(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read directories without permission.');
        }
        $this->tree();
        chmod($this->tempDir . '/tree/sub', 0000);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to read directory ' . $this->tempDir . '/tree: ');

        Directory::files($this->tempDir . '/tree');
    }

    public function testADirectoryWithANullCharacterCanNotBeListed(): void
    {
        $this->expectException(PathException::class);

        Directory::files("a\0b");
    }

    public function testADirectoryIsRemovedWithItsContent(): void
    {
        $this->tree();

        Directory::remove($this->tempDir . '/tree');

        $this->assertDirectoryDoesNotExist($this->tempDir . '/tree');
    }

    public function testASymbolicLinkInsideIsRemovedAndNotFollowed(): void
    {
        mkdir($this->tempDir . '/outside');
        file_put_contents($this->tempDir . '/outside/precious.txt', 'keep');
        mkdir($this->tempDir . '/links');
        file_put_contents($this->tempDir . '/links/file.txt', 'x');
        symlink($this->tempDir . '/outside', $this->tempDir . '/links/to-directory');
        symlink($this->tempDir . '/outside/precious.txt', $this->tempDir . '/links/to-file');

        Directory::remove($this->tempDir . '/links');

        $this->assertDirectoryDoesNotExist($this->tempDir . '/links');
        $this->assertSame('keep', file_get_contents($this->tempDir . '/outside/precious.txt'));
    }

    public function testASymbolicLinkToADirectoryIsRemovedAsALink(): void
    {
        mkdir($this->tempDir . '/outside');
        file_put_contents($this->tempDir . '/outside/precious.txt', 'keep');
        symlink($this->tempDir . '/outside', $this->tempDir . '/link');

        Directory::remove($this->tempDir . '/link');

        $this->assertFalse(is_link($this->tempDir . '/link'));
        $this->assertSame('keep', file_get_contents($this->tempDir . '/outside/precious.txt'));
    }

    public function testADirectoryThatDoesNotExistIsAlreadyRemoved(): void
    {
        Directory::remove($this->tempDir . '/missing');

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testAFileIsNotRemovedAsADirectory(): void
    {
        file_put_contents($this->tempDir . '/file.txt', 'x');

        $this->expectException(FileAccessException::class);
        $this->expectExceptionMessage('The path is not a directory: ' . $this->tempDir . '/file.txt');

        Directory::remove($this->tempDir . '/file.txt');
    }

    public function testADirectoryWhoseParentIsNotWritableCanNotBeRemoved(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can remove directories without permission.');
        }
        mkdir($this->tempDir . '/locked/inside', 0777, true);
        chmod($this->tempDir . '/locked', 0555);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to remove directory ' . $this->tempDir . '/locked/inside: ');

        Directory::remove($this->tempDir . '/locked/inside');
    }

    public function testADirectoryWithSomethingThatCanNotBeRemovedKeepsTheRest(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can remove files without permission.');
        }
        mkdir($this->tempDir . '/tree/locked', 0777, true);
        file_put_contents($this->tempDir . '/tree/locked/file.txt', 'x');
        chmod($this->tempDir . '/tree/locked', 0555);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to remove directory ' . $this->tempDir . '/tree/locked/file.txt: ');

        Directory::remove($this->tempDir . '/tree');
    }

    public function testADirectoryWithoutPermissionToReadItCanNotBeRemoved(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read directories without permission.');
        }
        mkdir($this->tempDir . '/closed');
        chmod($this->tempDir . '/closed', 0000);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to remove directory ' . $this->tempDir . '/closed: ');

        Directory::remove($this->tempDir . '/closed');
    }

    public function testADirectoryWithANullCharacterCanNotBeRemoved(): void
    {
        $this->expectException(PathException::class);

        Directory::remove("a\0b");
    }
}
