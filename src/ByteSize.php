<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\File;

/**
 * Reads a size of bytes as a person writes it (`2M`, `1.5Mi`).
 */
final class ByteSize
{
    /**
     * Reads a size, as written by a person, and returns it in bytes.
     *
     * It is a whole number of bytes (`2048`), or a number with a unit, that can
     * have decimals with a point (`2M`, `1.5Mi`). The units are not case
     * sensitive:
     *
     *   - `k`, `m` and `g` are decimal: 1000, 1000^2 and 1000^3 bytes.
     *   - `ki`, `mi` and `gi` are binary: 1024, 1024^2 and 1024^3 bytes.
     *
     * The result is rounded down to a whole byte. The calculation is done with
     * integers, so the rounding does not depend on how a float is stored.
     *
     * Nothing else is a size: not a unit alone, a number with spaces, a
     * negative number, a decimal without a unit, a unit that is not in the list
     * (`MB`), a number with more than 9 decimals (without the zeros at the end)
     * or a size that does not fit in an integer.
     *
     * @param string $size The size.
     * @return int|null The size in bytes, or `null` if it is not a valid size.
     */
    public static function parse(string $size): ?int
    {
        $factors = [
            '' => 1,
            'k' => 1000,
            'ki' => 1 << 10,
            'm' => 1000 ** 2,
            'mi' => 1 << 20,
            'g' => 1000 ** 3,
            'gi' => 1 << 30,
        ];

        if (!preg_match('/^(\d+)(?:\.(\d+))?(k|ki|m|mi|g|gi)?$/i', $size, $matches)) {
            return null;
        }

        $factor = $factors[strtolower($matches[3] ?? '')];
        $fraction = rtrim($matches[2] ?? '', '0');

        // Bytes can not have decimals, not even zeros, and 9 digits of them is
        // the most that can be multiplied by a factor without leaving the
        // integers.
        if (isset($matches[2]) && ($factor === 1 || strlen($fraction) > 9)) {
            return null;
        }

        $whole = filter_var(ltrim($matches[1], '0') ?: '0', FILTER_VALIDATE_INT);
        if ($whole === false || $whole > intdiv(PHP_INT_MAX, $factor)) {
            return null;
        }

        $bytes = $whole * $factor;
        if ($fraction === '') {
            return $bytes;
        }

        $extra = intdiv((int) $fraction * $factor, 10 ** strlen($fraction));

        return $bytes > PHP_INT_MAX - $extra ? null : $bytes + $extra;
    }
}
