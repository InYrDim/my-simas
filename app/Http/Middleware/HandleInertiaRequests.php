<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'provider' => $this->providerPayload($request),
            ],
        ];
    }

    /**
     * The signed-in provider operator on the console host (the default
     * web guard never carries it).
     *
     * @return array{name: string, email: string}|null
     */
    private function providerPayload(Request $request): ?array
    {
        $provider = $request->user('provider');

        if ($provider === null) {
            return null;
        }

        return ['name' => $provider->name, 'email' => $provider->email];
    }
}
