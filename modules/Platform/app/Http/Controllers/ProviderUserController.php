<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Infrastructure\Mock\ProviderMockData;

/**
 * Provider console operator accounts (read-only mock for now).
 */
final class ProviderUserController
{
    public function index(): Response
    {
        return Inertia::render('Platform/Users/Index', [
            'users' => ProviderMockData::providerUsers(),
        ]);
    }
}
