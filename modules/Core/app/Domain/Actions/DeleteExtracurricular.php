<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\Extracurricular;

final class DeleteExtracurricular
{
    public function handle(Extracurricular $extracurricular): void
    {
        DB::transaction(function () use ($extracurricular): void {
            $extracurricular->memberships()->delete();
            $extracurricular->delete();
        });
    }
}
