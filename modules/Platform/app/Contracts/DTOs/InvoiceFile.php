<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * An invoice rendered as a PDF.
 */
final readonly class InvoiceFile
{
    public function __construct(
        public string $filename,
        public string $contents,
    ) {}
}
