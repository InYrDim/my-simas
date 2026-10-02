<?php

namespace Modules\Core\App\Domain\Enums;

/**
 * What a CSV import brings in, with the columns its file carries.
 */
enum ImportTarget: string
{
    case Students = 'students';
    case Teachers = 'teachers';

    public function label(): string
    {
        return match ($this) {
            self::Students => 'Siswa',
            self::Teachers => 'Guru & Tendik',
        };
    }

    /**
     * Every column of the file, in template order.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Students => ['nama', 'nis', 'nisn', 'jenis_kelamin', 'tanggal_lahir', 'nama_wali', 'telepon_wali', 'kelas'],
            self::Teachers => ['nama', 'nip', 'nuptk', 'status_kepegawaian', 'tugas', 'email'],
        };
    }

    /**
     * The columns a file must have to be read at all.
     *
     * @return list<string>
     */
    public function requiredColumns(): array
    {
        return match ($this) {
            self::Students => ['nama', 'nis', 'jenis_kelamin'],
            self::Teachers => ['nama', 'status_kepegawaian', 'tugas'],
        };
    }

    /**
     * The example row of the downloadable template.
     *
     * @return list<string>
     */
    public function sampleRow(): array
    {
        return match ($this) {
            self::Students => ['Budi Santoso', '2024001', '0012345678', 'L', '2010-05-17', 'Slamet Santoso', '081234567890', 'X 1'],
            self::Teachers => ['Siti Aminah', '198501012010012001', '', 'PNS', 'Guru Mapel', 'siti@sekolah.sch.id'],
        };
    }

    public function templateFileName(): string
    {
        return match ($this) {
            self::Students => 'templat-siswa.csv',
            self::Teachers => 'templat-guru.csv',
        };
    }
}
