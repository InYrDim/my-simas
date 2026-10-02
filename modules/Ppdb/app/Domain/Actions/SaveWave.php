<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;

/**
 * Creates or updates a wave of a period. A wave closes on or after the day
 * it opens, and the waves of one period never overlap.
 */
final class SaveWave
{
    /**
     * @param  array{name: string, opens_on: string, closes_on: string}  $data
     *
     * @throws ValidationException when the dates are wrong or overlap another wave
     */
    public function handle(AdmissionPeriod $period, ?AdmissionWave $wave, array $data): AdmissionWave
    {
        if ($data['closes_on'] < $data['opens_on']) {
            throw ValidationException::withMessages([
                'closes_on' => 'Tanggal tutup tidak boleh sebelum tanggal buka.',
            ]);
        }

        $overlapping = $period->waves()
            ->when($wave?->exists, fn ($query) => $query->whereKeyNot($wave->id))
            ->where('opens_on', '<=', $data['closes_on'])
            ->where('closes_on', '>=', $data['opens_on'])
            ->first();

        if ($overlapping !== null) {
            throw ValidationException::withMessages([
                'opens_on' => "Tanggalnya bertumpuk dengan {$overlapping->name}.",
            ]);
        }

        $wave ??= new AdmissionWave;
        $wave->fill([...$data, 'period_id' => $period->id])->save();

        return $wave;
    }
}
