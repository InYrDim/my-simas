<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

/**
 * Message wording with `{name}` variables and `{a|b|c}` variations.
 *
 * A variation group offers wordings of the same message: one is picked per
 * group, at random, so the messages of a school do not all read the same.
 * Groups are resolved before the variables are filled, so a value that
 * contains `|` or `{` (a student's name) is never taken for a group.
 */
final class NoticeTemplate
{
    /** Variables Core fills in for every kind, with their preview samples. */
    public const COMMON = [
        'nama_siswa' => 'Aditya Pratama',
        'nama_wali' => 'Bapak Pratama',
        'nama_sekolah' => 'SMA Contoh',
    ];

    /** A group: braces around two or more choices, no braces inside. */
    private const GROUP = '/\{([^{}|]*(?:\|[^{}|]*)+)\}/';

    /**
     * Pick one choice per group, then put the values in. A variable
     * without a value stays as written, so a typo in a school's wording
     * shows up in the message instead of silently vanishing.
     *
     * @param  array<string, string>  $values
     * @param  (callable(int, int): int)|null  $choose  picks the choice of a group from its position and its number of choices; random when omitted
     */
    public static function fill(string $template, array $values, ?callable $choose = null): string
    {
        return self::putValues(self::resolveGroups($template, true, $choose), $values);
    }

    /**
     * The same, without chance: every group shows its first choice. For the
     * sample on the settings page.
     *
     * @param  array<string, string>  $values
     */
    public static function preview(string $template, array $values): string
    {
        return self::putValues(self::resolveGroups($template, false), $values);
    }

    /**
     * What is wrong with the groups of a template, or null when they are
     * fine: a group needs at least two choices that are not empty, and
     * groups cannot be nested.
     */
    public static function groupError(string $template): ?string
    {
        preg_match_all(self::GROUP, $template, $groups);

        foreach ($groups[1] as $inside) {
            $choices = array_filter(array_map('trim', explode('|', $inside)), fn (string $choice): bool => $choice !== '');

            if (count($choices) < count(explode('|', $inside))) {
                return "Pilihan variasi \"{{$inside}}\" tidak boleh kosong.";
            }
        }

        $rest = preg_replace(self::GROUP, '', $template) ?? $template;

        if (preg_match('/\{[^{}]*\||\|[^{}]*\}/', $rest) === 1) {
            return 'Variasi {a|b|c} harus ditutup dan tidak boleh bersarang.';
        }

        return null;
    }

    /**
     * @param  (callable(int, int): int)|null  $choose
     */
    private static function resolveGroups(string $template, bool $random, ?callable $choose = null): string
    {
        $position = 0;

        return (string) preg_replace_callback(
            self::GROUP,
            function (array $match) use ($random, $choose, &$position): string {
                $choices = explode('|', $match[1]);
                $index = $position++;

                if (! $random) {
                    return $choices[0];
                }

                return $choices[$choose === null ? array_rand($choices) : $choose($index, count($choices))];
            },
            $template,
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function putValues(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $match): string => $values[$match[1]] ?? $match[0],
            $template,
        );
    }
}
