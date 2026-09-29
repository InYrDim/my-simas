<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Provider console landing (post-login). Deliberately minimal in
 * Fase 1: real tenant administration lives in a later fase.
 */
final class ProviderHomeController
{
    public function __invoke(): Response
    {
        return Inertia::render('Platform/ProviderHome');
    }
}
