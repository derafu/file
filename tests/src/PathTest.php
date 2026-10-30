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

use Derafu\File\Exception\FileException;
use Derafu\File\Exception\FileNotFoundException;
use Derafu\File\Exception\PathException;
use Derafu\File\Path;
use Derafu\TestsFile\Fixture\UsesTemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    use UsesTemporaryDirectory;

    public function testAPathWithoutANullCharacterIsValid(): void
    {
        Path::assertValid('/tmp/a b/ñ.txt');
        Path::assertValid('');

        $this->expectNotToPerformAssertions();
    }

    public function testAPathWithANullCharacterIsNotValid(): void
    {
        $this->expectException(PathException::class);
        $this->expectExceptionMessage('The path contains a null character.');

        Path::assertValid("a\0b");
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function joins(): array
    {
        return [
            'two parts' => [['a', 'b'], 'a/b'],
            'separators at the joint' => [['a/', '/b'], 'a/b'],
            'several separators at the joint' => [['a//', '//b'], 'a/b'],
            'absolute first part' => [['/a', 'b', 'c.txt'], '/a/b/c.txt'],
            'root as the first part' => [['/', 'b'], '/b'],
            'empty parts are left out' => [['a', '', 'b', ''], 'a/b'],
            'empty first part' => [['', 'a'], 'a'],
            'one part' => [['a/'], 'a'],
            'only the root' => [['/'], '/'],
            'backslashes at the joint' => [['a\\', '\\b'], 'a/b'],
            'dots stay' => [['a', '..', 'b'], 'a/../b'],
            'nothing' => [[], ''],
            'only empty parts' => [['', ''], ''],
        ];
    }

    /**
     * @param list<string> $parts
     */
    #[DataProvider('joins')]
    public function testThePartsAreJoinedWithASingleSeparator(array $parts, string $expected): void
    {
        $this->assertSame($expected, Path::join(...$parts));
    }

    public function testAnExistingPathIsResolved(): void
    {
        mkdir($this->tempDir . '/a/b', 0777, true);
        $real = realpath($this->tempDir);
        $this->assertIsString($real);
        symlink($this->tempDir . '/a', $this->tempDir . '/link');

        $this->assertSame($real . '/a/b', Path::resolve($this->tempDir . '/a/./b/../b'));
        $this->assertSame($real . '/a', Path::resolve($this->tempDir . '/link'));
    }

    public function testAPathThatDoesNotExistCanNotBeResolved(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The path does not exist: ' . $this->tempDir . '/missing');

        Path::resolve($this->tempDir . '/missing');
    }

    public function testAnEmptyPathIsNotTheCurrentDirectory(): void
    {
        $this->expectException(FileNotFoundException::class);

        Path::resolve('');
    }

    public function testAPathWithANullCharacterCanNotBeResolved(): void
    {
        $this->expectException(PathException::class);

        Path::resolve("a\0b");
    }

    public function testAPathInsideTheDirectoryIsResolved(): void
    {
        mkdir($this->tempDir . '/base/sub', 0777, true);
        file_put_contents($this->tempDir . '/base/sub/file.txt', 'x');
        $base = realpath($this->tempDir . '/base');
        $this->assertIsString($base);

        $this->assertSame($base . '/sub/file.txt', Path::resolveWithin($this->tempDir . '/base', 'sub/file.txt'));
        $this->assertSame($base . '/sub', Path::resolveWithin($this->tempDir . '/base/', 'sub/'));
        $this->assertSame($base . '/sub/file.txt', Path::resolveWithin($this->tempDir . '/base', 'sub/../sub/./file.txt'));
    }

    public function testTheRelativePathIsTakenFromTheDirectoryEvenWithALeadingSeparator(): void
    {
        mkdir($this->tempDir . '/base/etc', 0777, true);
        $base = realpath($this->tempDir . '/base');
        $this->assertIsString($base);

        $this->assertSame($base . '/etc', Path::resolveWithin($this->tempDir . '/base', '/etc'));
    }

    public function testAnEmptyRelativePathIsTheDirectoryItself(): void
    {
        mkdir($this->tempDir . '/base/x', 0777, true);
        $base = realpath($this->tempDir . '/base');
        $this->assertIsString($base);

        $this->assertSame($base, Path::resolveWithin($this->tempDir . '/base', ''));
        $this->assertSame($base, Path::resolveWithin($this->tempDir . '/base', '.'));
        $this->assertSame($base, Path::resolveWithin($this->tempDir . '/base', 'x/..'));
    }

    public function testAPathThatLeavesTheDirectoryIsRefused(): void
    {
        mkdir($this->tempDir . '/base');
        file_put_contents($this->tempDir . '/secret.txt', 'x');

        $this->expectException(PathException::class);
        $this->expectExceptionMessage('The path ../secret.txt is outside of ' . $this->tempDir . '/base.');

        Path::resolveWithin($this->tempDir . '/base', '../secret.txt');
    }

    public function testASiblingDirectoryWithTheSameStartIsOutside(): void
    {
        mkdir($this->tempDir . '/base');
        mkdir($this->tempDir . '/base-other');
        file_put_contents($this->tempDir . '/base-other/file.txt', 'x');

        $this->expectException(PathException::class);

        Path::resolveWithin($this->tempDir . '/base', '../base-other/file.txt');
    }

    public function testASymbolicLinkThatLeavesTheDirectoryIsRefused(): void
    {
        mkdir($this->tempDir . '/base');
        mkdir($this->tempDir . '/outside');
        file_put_contents($this->tempDir . '/outside/file.txt', 'x');
        symlink($this->tempDir . '/outside', $this->tempDir . '/base/link');

        $this->expectException(PathException::class);

        Path::resolveWithin($this->tempDir . '/base', 'link/file.txt');
    }

    public function testASymbolicLinkThatStaysInsideTheDirectoryIsAccepted(): void
    {
        mkdir($this->tempDir . '/base/real', 0777, true);
        file_put_contents($this->tempDir . '/base/real/file.txt', 'x');
        symlink($this->tempDir . '/base/real', $this->tempDir . '/base/link');
        $base = realpath($this->tempDir . '/base');
        $this->assertIsString($base);

        $this->assertSame($base . '/real/file.txt', Path::resolveWithin($this->tempDir . '/base', 'link/file.txt'));
    }

    public function testTheRootCanBeTheDirectory(): void
    {
        $this->assertSame(realpath('/tmp'), Path::resolveWithin('/', 'tmp'));
    }

    public function testAPathInsideTheDirectoryThatDoesNotExistIsNotFound(): void
    {
        mkdir($this->tempDir . '/base');

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The path does not exist: missing.txt');

        Path::resolveWithin($this->tempDir . '/base', 'missing.txt');
    }

    public function testADirectoryThatDoesNotExistIsNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        Path::resolveWithin($this->tempDir . '/missing', 'file.txt');
    }

    public function testARelativePathWithANullCharacterIsRefused(): void
    {
        mkdir($this->tempDir . '/base');

        $this->expectException(PathException::class);
        $this->expectExceptionMessage('The path contains a null character.');

        Path::resolveWithin($this->tempDir . '/base', "a\0b");
    }

    public function testTheErrorsOfThePathAreFileErrors(): void
    {
        $this->expectException(FileException::class);

        Path::resolve($this->tempDir . '/missing');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function names(): array
    {
        return [
            'simple' => ['/a/b/report.xml', 'xml', 'report'],
            'uppercase is kept' => ['/a/REPORT.XML', 'XML', 'REPORT'],
            'only the last extension' => ['/a/archive.tar.gz', 'gz', 'archive.tar'],
            'no extension' => ['/a/README', '', 'README'],
            'hidden file' => ['/a/.env', 'env', ''],
            'trailing point' => ['/a/file.', '', 'file'],
            'no directory' => ['file.txt', 'txt', 'file'],
            'a point in the directory' => ['/a.b/file', '', 'file'],
            'empty' => ['', '', ''],
        ];
    }

    #[DataProvider('names')]
    public function testTheExtensionAndTheNameOfAPath(string $path, string $extension, string $filename): void
    {
        $this->assertSame($extension, Path::extension($path));
        $this->assertSame($filename, Path::filename($path));
    }
}
