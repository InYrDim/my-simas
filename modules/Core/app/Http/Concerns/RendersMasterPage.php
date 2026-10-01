<?php

namespace Modules\Core\App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Http\Resources\SchoolSummaryResource;

/**
 * Renders a master-data page with the school summary every page needs
 * (level, labels) already in its props.
 */
trait RendersMasterPage
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function renderMaster(string $component, array $props = []): Response
    {
        return Inertia::render($component, [
            'school' => SchoolSummaryResource::make(SchoolProfile::current())->resolve(),
            ...$props,
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, *>  $paginator
     * @return array{page: int, lastPage: int, total: int, from: int, to: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => (int) $paginator->firstItem(),
            'to' => (int) $paginator->lastItem(),
        ];
    }
}
