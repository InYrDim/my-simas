<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\CalendarEvent;

final class CalendarEventRequest extends AcademicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(CalendarEvent::CATEGORIES)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Judul',
            'category' => 'Kategori',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal selesai',
        ];
    }

    /**
     * @return array{title: string, category: string, start_date: string, end_date: string|null}
     */
    public function eventData(): array
    {
        $end = $this->validated('end_date');

        return [
            'title' => (string) $this->validated('title'),
            'category' => (string) $this->validated('category'),
            'start_date' => (string) $this->validated('start_date'),
            'end_date' => $end === null ? null : (string) $end,
        ];
    }
}
