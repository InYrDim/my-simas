<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\BuiltinFields;

/**
 * Gives a new period the usual form: the ten built-in fields in their usual
 * order and state. Run when a period is created, so every period has a form.
 */
final class SeedFormFields
{
    /**
     * Seeds the form of a period that has none: one inserted without the
     * model's `created` event (an import, a hand-made row) would otherwise
     * show an empty form. A period that already has fields is left alone,
     * and two requests seeding at once are safe: the unique index lets one
     * win and the other sees the fields it made.
     */
    public function ensure(AdmissionPeriod $period): void
    {
        if (FormField::query()->where('period_id', $period->id)->exists()) {
            return;
        }

        try {
            DB::transaction(fn () => $this->handle($period));
        } catch (UniqueConstraintViolationException) {
            // Another request seeded it first.
        }
    }

    public function handle(AdmissionPeriod $period): void
    {
        foreach (BuiltinFields::keys() as $order => $key) {
            FormField::query()->create([
                'period_id' => $period->id,
                'key' => $key,
                'type' => FieldType::Builtin,
                'label' => BuiltinFields::label($key),
                'required' => BuiltinFields::isRequiredByDefault($key),
                'sort_order' => $order,
            ]);
        }
    }
}
