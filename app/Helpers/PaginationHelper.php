<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginationHelper
{
    /**
     * Format pagination data for API responses.
     *
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    public static function format(LengthAwarePaginator $paginator): array
    {
        return [
            'total_pages'   => $paginator->lastPage(),
            'current_page'  => $paginator->currentPage(),
            'next_page'     => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'prev_page'     => $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
        ];
    }
}
