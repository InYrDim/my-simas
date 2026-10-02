<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use RuntimeException;

/**
 * The provider asked for a step that does not fit where a school's
 * WhatsApp stands (deciding twice, disabling what is not active, ...).
 */
final class InstanceStatusException extends RuntimeException
{
    public static function notPending(): self
    {
        return new self('Pengajuan WhatsApp ini sudah diputuskan.');
    }

    public static function notActive(): self
    {
        return new self('WhatsApp sekolah ini tidak sedang aktif.');
    }

    public static function notDisabled(): self
    {
        return new self('WhatsApp sekolah ini tidak sedang dinonaktifkan.');
    }
}
