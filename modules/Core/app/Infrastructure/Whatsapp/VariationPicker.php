<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Modules\Platform\App\Contracts\TenantCache;

/**
 * Fills a wording like NoticeTemplate::fill, but a group never shows the
 * choice it showed in the school's previous message of the same wording,
 * so two messages in a row do not read alike. The last choices are one
 * short string per wording in the school's cache: one read and one write
 * per message, because messages are queued while a student is scanned.
 */
final class VariationPicker
{
    private const KEY = 'whatsapp:variation:';

    private const TTL = 86400;

    public function __construct(
        private readonly TenantCache $cache,
    ) {}

    /**
     * @param  array<string, string>  $values
     */
    public function fill(string $template, array $values): string
    {
        $key = self::KEY.md5($template);
        $last = array_map('intval', explode(',', (string) $this->cache->get($key, '')));
        $picked = [];

        $text = NoticeTemplate::fill($template, $values, function (int $group, int $count) use ($last, &$picked): int {
            $avoid = $last[$group] ?? -1;
            $options = array_values(array_filter(range(0, $count - 1), fn (int $choice): bool => $choice !== $avoid));

            return $picked[$group] = $options[array_rand($options)];
        });

        if ($picked !== []) {
            $this->cache->put($key, implode(',', $picked), self::TTL);
        }

        return $text;
    }
}
