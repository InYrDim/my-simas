<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * A kind of WhatsApp notice a module can send to guardians ("siswa tidak
 * hadir", ...). The school switches each kind on or off and may reword
 * its message on Integrasi › WhatsApp.
 */
final readonly class NoticeKind
{
    /**
     * @param  string  $key  stable identifier, unique across modules (`attendance.absent`)
     * @param  string  $title  shown in the school's list of notices
     * @param  string  $description  one sentence: when it is sent
     * @param  string  $recipient  who receives it, as a label (`Wali murid`)
     * @param  string  $template  default wording; `{name}` marks a variable
     * @param  array<string, string>  $variables  the kind's own variables: name => sample value for the preview. Core adds `nama_siswa`, `nama_wali` and `nama_sekolah` to every kind
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $recipient,
        public string $template,
        public array $variables = [],
    ) {}
}
