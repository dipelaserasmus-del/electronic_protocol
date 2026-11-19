<?php

namespace App\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * R2e review, R2g: a page past the last one (an old bookmark, rows removed since, or typed in the address) shows the
 * last page instead of an empty list. For components that use Livewire's WithPagination.
 */
trait PagesWithinRange
{
    /** @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $query */
    protected function pageOf($query, int $perPage): LengthAwarePaginator
    {
        $page = (clone $query)->paginate($perPage);
        if ($page->currentPage() > $page->lastPage()) {
            $this->setPage($page->lastPage());
            $page = (clone $query)->paginate($perPage, page: $page->lastPage());
        }

        return $page;
    }
}
