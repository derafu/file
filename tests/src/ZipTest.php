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
use Derafu\File\Zip;
use Derafu\TestsFile\Fixture\UsesTemporaryDirectory;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(Zip::class)]
#[UsesClass(Path::class)]
final class ZipTest extends TestCase
{
    use UsesTemporaryDirectory;

    /**
     * Reads an archive that is in memory: the name and the content of each entry.
     *
     * @return array<string, string>
     */
    private function entriesOf(string $archive): array
    {
        $file = $this->tempDir . '/read-' . uniqid('', true) . '.zip';
        file_put_contents($file, $archive);

        return $this->entriesOfFile($file);
    }

    /**
     * @return array<string, string>
     */
    private function entriesOfFile(string $file): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $entries[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $entries;
    }

    public function testAnArchiveIsCreatedFromContentsInMemory(): void
    {
        $archive = Zip::fromContents([
            'RecepcionDTE.xml' => '<a/>',
            'reports/EnvioRecibos.xml' => '<b/>',
            'empty.txt' => '',
        ]);

        $this->assertStringStartsWith('PK', $archive);
        $this->assertSame([
            'RecepcionDTE.xml' => '<a/>',
            'reports/EnvioRecibos.xml' => '<b/>',
            'empty.txt' => '',
        ], $this->entriesOf($archive));
    }

    public function testTheEntriesKeepTheOrderOfTheArrayAndTheirBinaryContent(): void
    {
        $binary = random_bytes(2048);

        $archive = Zip::fromContents(['z.bin' => $binary, 'a.txt' => 'ñ', '10' => 'numeric name']);

        $this->assertSame(['z.bin', 'a.txt', '10'], array_map('strval', array_keys($this->entriesOf($archive))));
        $this->assertSame($binary, $this->entriesOf($archive)['z.bin']);
        $this->assertSame('ñ', $this->entriesOf($archive)['a.txt']);
    }

    public function testNoTemporaryFileIsNeededToCreateAnArchive(): void
    {
        $before = glob(sys_get_temp_dir() . '/*') ?: [];

        Zip::fromContents(['a.txt' => 'a']);

        $this->assertSame($before, glob(sys_get_temp_dir() . '/*') ?: []);
    }

    public function testAnArchiveWithoutEntriesIsAValidArchive(): void
    {
        $archive = Zip::fromContents([]);

        $this->assertStringStartsWith('PK', $archive);
        $this->assertSame([], $this->entriesOf($archive));
    }

    public function testNoHttpHeadersAreSentWhenTheArchiveGoesToMemory(): void
    {
        // In the command line the headers are not sent, but Xdebug keeps the ones
        // that were asked for (also the ones of the tests that came before).
        $this->assertTrue(function_exists('xdebug_get_headers'), 'Xdebug is needed to see the headers.');
        $before = xdebug_get_headers();

        Zip::fromContents(['a.txt' => 'a']);

        $this->assertSame($before, xdebug_get_headers());
    }

    public function testAnEntryWithoutNameIsRefused(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The name of a ZIP entry must not be empty.');

        Zip::fromContents(['' => 'content']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'null character' => ["a\0b.txt"],
            'backslash' => ['a\\b.txt'],
            'absolute' => ['/a.txt'],
            'parent directory' => ['../a.txt'],
            'parent directory in the middle' => ['a/../b.txt'],
            'only the parent directory' => ['..'],
            'parent directory at the end' => ['a/..'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testAnEntryWithANameThatLeavesTheDirectoryOrWouldBeChangedIsRefused(string $name): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The name of the ZIP entry "' . $name . '" is not valid.');

        Zip::fromContents([$name => 'content']);
    }

    public function testNamesThatOnlyLookLikeParentDirectoriesAreAccepted(): void
    {
        $archive = Zip::fromContents(['..hidden' => 'a', 'a..b/c..txt' => 'b', 'dir/' => '']);

        $this->assertSame(['..hidden', 'a..b/c..txt', 'dir/'], array_keys($this->entriesOf($archive)));
    }

    public function testAnArchiveThatCanNotBeCreatedIsAFileError(): void
    {
        // A name has at most 65535 bytes.
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to create ZIP file: ');

        Zip::fromContents([str_repeat('a', 70000) => 'content']);
    }

    public function testAnEntryWhoseContentIsNotAStringIsRefused(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The content of the ZIP entry "report.xml" must be a string, null given.');

        Zip::fromContents(['report.xml' => null]);
    }

    public function testAFileIsCompressedWithItsName(): void
    {
        Zip::fromPath($this->fixture('zip/single/file.txt'), $this->tempDir . '/single.zip');

        $this->assertSame(['file.txt'], array_keys($this->entriesOfFile($this->tempDir . '/single.zip')));
    }

    public function testADirectoryIsCompressedWithItsContent(): void
    {
        Zip::fromPath($this->fixture('zip/multiple'), $this->tempDir . '/multiple.zip');

        $entries = array_keys($this->entriesOfFile($this->tempDir . '/multiple.zip'));
        sort($entries);
        $this->assertSame(['file1.txt', 'file2.txt'], $entries);
    }

    public function testTheEntriesAreNamedByTheirPathInsideADirectoryReachedThroughASymbolicLink(): void
    {
        mkdir($this->tempDir . '/the-real-directory-with-a-long-name/sub', 0777, true);
        file_put_contents($this->tempDir . '/the-real-directory-with-a-long-name/file.txt', 'a');
        file_put_contents($this->tempDir . '/the-real-directory-with-a-long-name/sub/inner.txt', 'b');
        symlink($this->tempDir . '/the-real-directory-with-a-long-name', $this->tempDir . '/link');

        Zip::fromPath($this->tempDir . '/link', $this->tempDir . '/link.zip');

        $entries = array_keys($this->entriesOfFile($this->tempDir . '/link.zip'));
        sort($entries);
        $this->assertSame(['file.txt', 'sub/inner.txt'], $entries);
    }

    public function testTheEntriesAreNamedTheSameWithATrailingSlash(): void
    {
        mkdir($this->tempDir . '/with-slash/sub', 0777, true);
        file_put_contents($this->tempDir . '/with-slash/file.txt', 'a');
        file_put_contents($this->tempDir . '/with-slash/sub/inner.txt', 'b');

        Zip::fromPath($this->tempDir . '/with-slash/', $this->tempDir . '/with-slash.zip');

        $entries = array_keys($this->entriesOfFile($this->tempDir . '/with-slash.zip'));
        sort($entries);
        $this->assertSame(['file.txt', 'sub/inner.txt'], $entries);
    }

    public function testNoHttpHeadersAreSentWhenTheArchiveGoesToAFile(): void
    {
        $this->assertTrue(function_exists('xdebug_get_headers'), 'Xdebug is needed to see the headers.');
        $before = xdebug_get_headers();

        Zip::fromPath($this->fixture('zip/single/file.txt'), $this->tempDir . '/quiet.zip');

        $this->assertSame($before, xdebug_get_headers());
    }

    public function testASourceThatDoesNotExistCanNotBeCompressed(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The path does not exist: ' . $this->tempDir . '/missing');

        Zip::fromPath($this->tempDir . '/missing', $this->tempDir . '/missing.zip');
    }

    public function testNothingIsCreatedWhenTheSourceDoesNotExist(): void
    {
        try {
            Zip::fromPath($this->tempDir . '/missing', $this->tempDir . '/missing.zip');
            $this->fail('The source does not exist.');
        } catch (FileNotFoundException) {
            $this->assertFileDoesNotExist($this->tempDir . '/missing.zip');
        }
    }

    public function testTheArchiveIsNotLeftHalfMadeWhenAFileCanNotBeRead(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read files without permission.');
        }
        mkdir($this->tempDir . '/source');
        file_put_contents($this->tempDir . '/source/secret.txt', 'x');
        chmod($this->tempDir . '/source/secret.txt', 0000);

        try {
            Zip::fromPath($this->tempDir . '/source', $this->tempDir . '/source.zip');
            $this->fail('A file that can not be read can not be compressed.');
        } catch (FileException $e) {
            $this->assertStringStartsWith('Failed to create ZIP file: ', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->tempDir . '/source.zip');
    }

    public function testAnArchiveCanNotBeCreatedWhereTheDestinationCanNotBeOpened(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Cannot open destination file for writing: ' . $this->tempDir . '/missing/file.zip');

        Zip::fromPath($this->fixture('zip/single/file.txt'), $this->tempDir . '/missing/file.zip');
    }

    public function testPathsWithANullCharacterAreRefused(): void
    {
        foreach ([
            static fn () => Zip::fromPath("a\0b", 'x.zip'),
            static fn () => Zip::fromPath(__FILE__, "a\0b"),
            static fn () => Zip::extract("a\0b", 'x'),
            static fn () => Zip::extract(__FILE__, "a\0b"),
        ] as $call) {
            try {
                $call();
                $this->fail('A path with a null character was accepted.');
            } catch (PathException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnArchiveIsExtracted(): void
    {
        $this->extractableArchive();

        Zip::extract($this->tempDir . '/archive.zip', $this->tempDir . '/out');

        $this->assertSame('a', file_get_contents($this->tempDir . '/out/a.txt'));
        $this->assertSame('b', file_get_contents($this->tempDir . '/out/sub/b.txt'));
    }

    public function testAFileThatExistsStopsTheExtractionUnlessItIsOverwritten(): void
    {
        $this->extractableArchive();
        mkdir($this->tempDir . '/out');
        file_put_contents($this->tempDir . '/out/a.txt', 'old');

        try {
            Zip::extract($this->tempDir . '/archive.zip', $this->tempDir . '/out');
            $this->fail('A file that exists was replaced.');
        } catch (FileException $e) {
            $this->assertSame('File already exists: ' . $this->tempDir . '/out/a.txt', $e->getMessage());
        }
        $this->assertSame('old', file_get_contents($this->tempDir . '/out/a.txt'));
        $this->assertFileDoesNotExist($this->tempDir . '/out/sub/b.txt', 'Nothing is extracted.');

        Zip::extract($this->tempDir . '/archive.zip', $this->tempDir . '/out', overwrite: true);

        $this->assertSame('a', file_get_contents($this->tempDir . '/out/a.txt'));
    }

    public function testADirectoryThatExistsIsNotAFileThatExists(): void
    {
        $this->extractableArchive();
        mkdir($this->tempDir . '/out/sub', 0777, true);

        Zip::extract($this->tempDir . '/archive.zip', $this->tempDir . '/out');

        $this->assertSame('b', file_get_contents($this->tempDir . '/out/sub/b.txt'));
    }

    public function testTheDirectoryEntriesOfAnArchiveAreNotFilesThatExist(): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->tempDir . '/with-directories.zip', ZipArchive::CREATE));
        $zip->addEmptyDir('sub');
        $zip->addFromString('sub/b.txt', 'b');
        $zip->close();
        mkdir($this->tempDir . '/out/sub', 0777, true);

        Zip::extract($this->tempDir . '/with-directories.zip', $this->tempDir . '/out');

        $this->assertSame('b', file_get_contents($this->tempDir . '/out/sub/b.txt'));
    }

    public function testAnArchiveThatDoesNotExistCanNotBeExtracted(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The file does not exist: ' . $this->tempDir . '/missing.zip');

        Zip::extract($this->tempDir . '/missing.zip', $this->tempDir . '/out');
    }

    public function testAFileThatIsNotAnArchiveCanNotBeExtracted(): void
    {
        file_put_contents($this->tempDir . '/not-a-zip.zip', 'this is not an archive');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to open ZIP file: ' . $this->tempDir . '/not-a-zip.zip (Error code: ');

        Zip::extract($this->tempDir . '/not-a-zip.zip', $this->tempDir . '/out');
    }

    public function testAnArchiveCanNotBeExtractedWhereTheFilesCanNotBeWritten(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can write files without permission.');
        }
        $this->extractableArchive();
        mkdir($this->tempDir . '/read-only');
        chmod($this->tempDir . '/read-only', 0555);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Failed to extract ZIP file to: ' . $this->tempDir . '/read-only');

        Zip::extract($this->tempDir . '/archive.zip', $this->tempDir . '/read-only');
    }

    public function testTheErrorsOfTheArchivesAreFileErrors(): void
    {
        $this->expectException(FileException::class);

        Zip::extract($this->tempDir . '/missing.zip', $this->tempDir . '/out');
    }

    private function extractableArchive(): void
    {
        file_put_contents(
            $this->tempDir . '/archive.zip',
            Zip::fromContents(['a.txt' => 'a', 'sub/b.txt' => 'b'])
        );
    }
}
