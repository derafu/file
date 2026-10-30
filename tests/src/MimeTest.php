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

use Derafu\File\Mime;
use Derafu\TestsFile\Fixture\UsesTemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mime::class)]
final class MimeTest extends TestCase
{
    use UsesTemporaryDirectory;

    private function content(string $name): string
    {
        $content = file_get_contents($this->fixture('files/' . $name));
        $this->assertIsString($content);

        return $content;
    }

    public function testTheTypeOfAFileIsDetected(): void
    {
        $this->assertSame('text/plain', Mime::fromFile($this->fixture('files/text.txt')));
        $this->assertSame('image/jpeg', Mime::fromFile($this->fixture('files/image.jpeg')));
        $this->assertSame('application/pdf', Mime::fromFile($this->fixture('files/document.pdf')));
    }

    public function testTheTypeIsDetectedByTheContentAndNotByTheName(): void
    {
        copy($this->fixture('files/image.jpeg'), $this->tempDir . '/image-named-as-text.txt');
        copy($this->fixture('files/text.txt'), $this->tempDir . '/text-named-as-image.jpeg');
        copy($this->fixture('files/document.pdf'), $this->tempDir . '/document');

        $this->assertSame('image/jpeg', Mime::fromFile($this->tempDir . '/image-named-as-text.txt'));
        $this->assertSame('text/plain', Mime::fromFile($this->tempDir . '/text-named-as-image.jpeg'));
        $this->assertSame('application/pdf', Mime::fromFile($this->tempDir . '/document'));
    }

    public function testAnEmptyFileAndBinaryContentHaveTheirOwnTypes(): void
    {
        touch($this->tempDir . '/empty.txt');
        file_put_contents($this->tempDir . '/data.bin', "\x00\x01\x02\x03\xff\xfe\xfd\xfc");

        $this->assertSame('application/x-empty', Mime::fromFile($this->tempDir . '/empty.txt'));
        $this->assertSame('application/octet-stream', Mime::fromFile($this->tempDir . '/data.bin'));
    }

    public function testASymbolicLinkIsFollowed(): void
    {
        symlink($this->fixture('files/image.jpeg'), $this->tempDir . '/link.txt');

        $this->assertSame('image/jpeg', Mime::fromFile($this->tempDir . '/link.txt'));
    }

    public function testWhatIsNotAReadableFileHasNoType(): void
    {
        symlink($this->tempDir . '/nonexistent.file', $this->tempDir . '/broken.txt');

        $this->assertNull(Mime::fromFile('nonexistent.file'));
        $this->assertNull(Mime::fromFile($this->tempDir . '/broken.txt'));
        $this->assertNull(Mime::fromFile($this->tempDir));
        $this->assertNull(Mime::fromFile(''));
        $this->assertNull(Mime::fromFile("a\0b"));
    }

    public function testAFileThatCanNotBeReadHasNoType(): void
    {
        if ($this->ignoresPermissions()) {
            $this->markTestSkipped('The user can read files without permission.');
        }
        file_put_contents($this->tempDir . '/unreadable.txt', 'text');
        chmod($this->tempDir . '/unreadable.txt', 0000);

        $this->assertNull(Mime::fromFile($this->tempDir . '/unreadable.txt'));
    }

    public function testTheTypeOfAContentIsDetected(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        $this->assertSame('image/jpeg', Mime::fromContent($this->content('image.jpeg')));
        $this->assertSame('application/pdf', Mime::fromContent($this->content('document.pdf')));
        $this->assertSame('text/plain', Mime::fromContent($this->content('text.txt')));
        $this->assertSame('image/png', Mime::fromContent((string) $png));
        $this->assertSame('application/x-empty', Mime::fromContent(''));
        $this->assertSame('application/octet-stream', Mime::fromContent("\x00\x01\x02\x03\xff\xfe\xfd\xfc"));
    }

    public function testTheStartOfAContentIsEnough(): void
    {
        $this->assertSame('image/jpeg', Mime::fromContent(substr($this->content('image.jpeg'), 0, 512)));
        $this->assertSame('application/pdf', Mime::fromContent(substr($this->content('document.pdf'), 0, 64)));
    }

    public function testAContentHasTheSameTypeAsItsFile(): void
    {
        foreach (['image.jpeg', 'document.pdf', 'text.txt'] as $name) {
            $this->assertSame(
                Mime::fromFile($this->fixture('files/' . $name)),
                Mime::fromContent($this->content($name))
            );
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function knownExtensions(): array
    {
        return [
            'csv' => ['csv', 'text/csv'],
            'jpg' => ['jpg', 'image/jpeg'],
            'jpeg' => ['jpeg', 'image/jpeg'],
            'png' => ['png', 'image/png'],
            'pdf' => ['pdf', 'application/pdf'],
            'txt' => ['txt', 'text/plain'],
        ];
    }

    #[DataProvider('knownExtensions')]
    public function testAKnownExtensionHasItsTypes(string $extension, string $expected): void
    {
        $this->assertContains($expected, Mime::typesOfExtension($extension));
    }

    public function testAnExtensionHasItsAliases(): void
    {
        $types = Mime::typesOfExtension('jpg');

        $this->assertContains('image/jpeg', $types);
        $this->assertContains('image/pjpeg', $types);
    }

    public function testTheCaseAndTheLeadingPointOfTheExtensionDoNotMatter(): void
    {
        $expected = Mime::typesOfExtension('csv');

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, Mime::typesOfExtension('CSV'));
        $this->assertSame($expected, Mime::typesOfExtension('.csv'));
        $this->assertSame($expected, Mime::typesOfExtension('.CSV'));
    }

    public function testWhatIsNotAKnownExtensionHasNoTypes(): void
    {
        $this->assertSame([], Mime::typesOfExtension('thisisnotanextension'));
        $this->assertSame([], Mime::typesOfExtension(''));
        $this->assertSame([], Mime::typesOfExtension('.'));
        $this->assertSame([], Mime::typesOfExtension('..csv'));
    }
}
