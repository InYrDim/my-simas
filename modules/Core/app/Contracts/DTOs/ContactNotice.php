<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One notice to a person who is not (yet) a student of the school — an
 * admissions applicant's guardian, for example — addressed by name and phone
 * number instead of a student id.
 */
final readonly class ContactNotice
{
    /**
     * @param  string  $kind  key of a registered NoticeKind
     * @param  string  $recipientName  who the message is for; fills `{nama_wali}`
     * @param  string|null  $phone  as typed; without a usable number the message is only logged
     * @param  string  $subjectName  who the message is about; fills `{nama_siswa}`
     * @param  array<string, string>  $variables  values for the kind's own variables
     */
    public function __construct(
        public string $kind,
        public string $recipientName,
        public ?string $phone,
        public string $subjectName,
        public array $variables = [],
    ) {}
}
