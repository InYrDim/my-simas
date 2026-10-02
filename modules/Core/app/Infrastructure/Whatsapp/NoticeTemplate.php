<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

/**
 * Message wording with `{name}` variables.
 */
final class NoticeTemplate
{
    /** Variables Core fills in for every kind, with their preview samples. */
    public const COMMON = [
        'nama_siswa' => 'Aditya Pratama',
        'nama_wali' => 'Bapak Pratama',
        'nama_sekolah' => 'SMA Contoh',
    ];

    /**
     * Put the values in. A variable without a value stays as written, so
     * a typo in a school's wording shows up in the message instead of
     * silently vanishing.
     *
     * @param  array<string, string>  $values
     */
    public static function fill(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $match): string => $values[$match[1]] ?? $match[0],
            $template,
        );
    }
}
