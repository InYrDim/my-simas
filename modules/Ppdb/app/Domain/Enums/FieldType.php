<?php

namespace Modules\Ppdb\App\Domain\Enums;

/**
 * What a field of the registration form is. `Builtin` is one of the ten
 * fields of the applicant record itself; the rest are the custom fields a
 * school adds. `Section` is a heading that groups fields and takes no answer.
 */
enum FieldType: string
{
    case Builtin = 'builtin';
    case Text = 'text';
    case Paragraph = 'paragraph';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkboxes = 'checkboxes';
    case File = 'file';
    case Section = 'section';

    public function label(): string
    {
        return match ($this) {
            self::Builtin => 'Isian bawaan',
            self::Text => 'Teks singkat',
            self::Paragraph => 'Paragraf',
            self::Number => 'Angka',
            self::Date => 'Tanggal',
            self::Select => 'Pilihan tunggal',
            self::Checkboxes => 'Pilihan ganda',
            self::File => 'Unggah berkas',
            self::Section => 'Judul bagian',
        };
    }

    /**
     * Whether the field takes an answer (a section does not).
     */
    public function takesAnswer(): bool
    {
        return $this !== self::Section;
    }

    /**
     * Whether the answer is one of a list of options the school wrote.
     */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::Checkboxes;
    }

    /**
     * The types a school may add (everything but the built-in fields).
     *
     * @return list<self>
     */
    public static function custom(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type !== self::Builtin));
    }
}
