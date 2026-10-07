<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One block of a person's Beranda. A module describes what it knows (a
 * number, a short list, a few bars, a status line, one action) in scalars
 * and Core decides where it goes: `slot` is the band of the page, `kind`
 * the shape of `payload`.
 *
 * `permission` is the Gate ability that decides who sees it; null means
 * everyone signed in. Core holds no role names: the roles a person has
 * only show up as the permissions their widgets ask for.
 *
 * Payload shapes per kind:
 * - `stat`: `value` (int|float|string), optional `hint`.
 * - `list`: `items` (each `label`, optional `detail`, `status` (a state word, shown as a badge), `value` (a plain figure) and `href`) and `empty` (the sentence for no items). Core shows at most MAX_ITEMS rows and adds `total`; a provider that already limits its rows sends its own `total`, and the widget's `href` is the link to the full list.
 * - `bars`: `points` (each `label`, `value`), optional `note`.
 * - `status`: `state` (`confirmed`, `pending`, `void` or `none`), `word`, optional `detail`.
 * - `action`: `label`; the target is `href`.
 */
final readonly class DashboardWidget
{
    /** Most rows a `list` shows; the widget's `href` leads to the rest. */
    public const MAX_ITEMS = 5;

    public const KIND_STAT = 'stat';

    public const KIND_LIST = 'list';

    public const KIND_BARS = 'bars';

    public const KIND_STATUS = 'status';

    public const KIND_ACTION = 'action';

    public const SLOT_ACTION = 'action';

    public const SLOT_FIGURES = 'figures';

    public const SLOT_ATTENTION = 'attention';

    public const SLOT_MAIN = 'main';

    /**
     * @param  self::KIND_*  $kind
     * @param  self::SLOT_*  $slot
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $key,
        public string $kind,
        public string $slot,
        public string $title,
        public array $payload = [],
        public int $order = 100,
        public ?string $href = null,
        public ?string $permission = null,
    ) {}
}
