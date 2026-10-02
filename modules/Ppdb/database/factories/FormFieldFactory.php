<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\FormField;

/**
 * Defaults to an optional short-text custom field. `period_id` must be given.
 *
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => null,
            'type' => FieldType::Text,
            'label' => 'Pertanyaan',
            'required' => false,
            'sort_order' => 100,
        ];
    }

    public function ofType(FieldType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['required' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }

    /**
     * @param  list<string>  $options
     */
    public function withOptions(array $options): static
    {
        return $this->state(fn (): array => ['options' => $options]);
    }
}
