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

use Derafu\File\ByteSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ByteSize::class)]
final class ByteSizeTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validSizes(): array
    {
        return [
            'zero' => ['0', 0],
            'bytes' => ['2048', 2048],
            'bytes with leading zeros' => ['007', 7],
            'biggest integer' => [(string) PHP_INT_MAX, PHP_INT_MAX],
            'size with a unit that fits' => ['9223372036854775k', 9223372036854775000],
            'decimal kilo' => ['2k', 2000],
            'binary kilo' => ['2ki', 2048],
            'decimal mega' => ['2m', 2000000],
            'binary mega' => ['2mi', 2097152],
            'decimal giga' => ['2g', 2000000000],
            'binary giga' => ['2gi', 2147483648],
            'upper case' => ['2MI', 2097152],
            'mixed case' => ['2Mi', 2097152],
            'decimals' => ['1.5Mi', 1572864],
            'decimals of a decimal unit' => ['1.5M', 1500000],
            'decimals that are not exact in a float' => ['1.15k', 1150],
            'decimals with the zeros at the end' => ['1.50000000000000M', 1500000],
            'only zeros as decimals' => ['2.0M', 2000000],
            'zero with decimals' => ['0.5k', 500],
            'rounded down' => ['1.1ki', 1126],
            'nine decimals' => ['0.123456789g', 123456789],
        ];
    }

    #[DataProvider('validSizes')]
    public function testAValidSizeIsReadInBytes(string $size, int $expected): void
    {
        $this->assertSame($expected, ByteSize::parse($size));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSizes(): array
    {
        return [
            'empty' => [''],
            'unit alone' => ['M'],
            'unit that is not in the list' => ['2MB'],
            'unit of bytes' => ['2B'],
            'unit that does not exist' => ['2X'],
            'text' => ['abc'],
            'negative' => ['-1M'],
            'plus sign' => ['+1M'],
            'decimals without a whole part' => ['.5M'],
            'decimal point without decimals' => ['1.M'],
            'decimal separator that is a comma' => ['1,5M'],
            'exponent' => ['1e3M'],
            'space before the unit' => ['2 M'],
            'space around' => [' 2M '],
            'decimals without a unit' => ['1.5'],
            'decimals of zero without a unit' => ['1.0'],
            'more than 9 decimals' => ['1.1234567891M'],
            'size that does not fit' => ['9223372036854775808'],
            'size with a unit that does not fit' => ['9223372036854776k'],
            'size with decimals that does not fit' => ['9223372036854775.9k'],
        ];
    }

    #[DataProvider('invalidSizes')]
    public function testAnInvalidSizeIsNull(string $size): void
    {
        $this->assertNull(ByteSize::parse($size));
    }
}
