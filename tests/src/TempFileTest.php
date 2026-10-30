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

use Derafu\File\TempFile;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TempFile::class)]
final class TempFileTest extends TestCase
{
    private const PREFIX = 'derafu_file_test_';

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/' . self::PREFIX . '*') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * @return list<string>
     */
    private function leftovers(): array
    {
        return glob(sys_get_temp_dir() . '/' . self::PREFIX . '*') ?: [];
    }

    public function testTheCallbackReceivesAFileWithTheContentAndItsResultIsReturned(): void
    {
        $seen = null;

        $result = TempFile::with('the content', function (string $path) use (&$seen): int {
            $seen = [file_get_contents($path), dirname($path), basename($path)];

            return 42;
        }, prefix: self::PREFIX);

        $this->assertSame(42, $result);
        $this->assertNotNull($seen);
        $this->assertSame('the content', $seen[0]);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath($seen[1]));
        $this->assertStringStartsWith(self::PREFIX, $seen[2]);
        $this->assertSame([], $this->leftovers());
    }

    public function testTheFileIsEmptyWhenThereIsNoContentAndTheCallbackCanWriteIt(): void
    {
        $content = TempFile::with('', function (string $path): string {
            $this->assertSame('', file_get_contents($path));
            file_put_contents($path, 'written by the callback');

            return (string) file_get_contents($path);
        }, prefix: self::PREFIX);

        $this->assertSame('written by the callback', $content);
        $this->assertSame([], $this->leftovers());
    }

    /**
     * @return array<string, array{string, non-empty-string}>
     */
    public static function extensions(): array
    {
        return [
            'simple' => ['xml', '.xml'],
            'with the point' => ['.xml', '.xml'],
            'more than one' => ['xml.gz', '.xml.gz'],
            'with the point and more than one' => ['.xml.gz', '.xml.gz'],
        ];
    }

    /**
     * @param non-empty-string $expectedEnd
     */
    #[DataProvider('extensions')]
    public function testTheFileHasTheExtension(string $extension, string $expectedEnd): void
    {
        $path = TempFile::with('x', static fn (string $path): string => $path, $extension, self::PREFIX);

        $this->assertStringEndsWith($expectedEnd, $path);
        $this->assertSame([], $this->leftovers(), 'The file without the extension is not left either.');
    }

    public function testTheFileIsDeletedAndTheExceptionOfTheCallbackIsTheOneThatGoesUp(): void
    {
        $path = null;
        $caught = null;

        try {
            TempFile::with('x', function (string $given) use (&$path): string {
                $path = $given;
                if (file_exists($given)) {
                    throw new LogicException('from the callback');
                }

                return $given;
            }, prefix: self::PREFIX);
        } catch (LogicException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'The exception of the callback was lost.');
        $this->assertSame('from the callback', $caught->getMessage());
        $this->assertIsString($path);
        $this->assertFileDoesNotExist($path);
    }

    public function testTheFileCanBeDeletedByTheCallback(): void
    {
        TempFile::with('x', static function (string $path): void {
            unlink($path);
        }, prefix: self::PREFIX);

        $this->assertSame([], $this->leftovers());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidExtensions(): array
    {
        return [
            'with a separator' => ['a/b'],
            'with a parent directory' => ['../x'],
            'with a space' => ['x y'],
            'with a null character' => ["x\0"],
            'only points' => ['..'],
            'a point at the end' => ['xml.'],
        ];
    }

    #[DataProvider('invalidExtensions')]
    public function testAnInvalidExtensionIsRefused(string $extension): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        TempFile::with('x', static fn (string $path): string => $path, $extension, self::PREFIX);
    }

    public function testAnInvalidPrefixIsRefused(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The prefix "../x" is not valid.');

        TempFile::with('x', static fn (string $path): string => $path, prefix: '../x');
    }
}
