<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;

/**
 * Defaults to a text answer. `applicant_id` and `field_id` must be given.
 *
 * @extends Factory<ApplicantAnswer>
 */
class ApplicantAnswerFactory extends Factory
{
    protected $model = ApplicantAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['value' => 'Jawaban'];
    }
}
