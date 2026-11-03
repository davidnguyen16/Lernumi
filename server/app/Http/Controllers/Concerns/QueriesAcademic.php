<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Academic\IndexAcademicRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

trait QueriesAcademic
{
    private function paginateAcademic(Builder $query, IndexAcademicRequest $request, array $sorts, string $default, array $secondary = []): LengthAwarePaginator
    {
        $sort = $request->validated('sort', $default);
        $descending = str_starts_with($sort, '-');
        $column = $sorts[ltrim($sort, '-')] ?? $sorts[ltrim($default, '-')];
        $query->orderBy($column, $descending ? 'desc' : 'asc');
        foreach ($secondary as $secondaryColumn) $query->orderBy($secondaryColumn, $descending ? 'desc' : 'asc');
        return $query->orderBy($query->getModel()->qualifyColumn('id'))
            ->paginate((int) $request->validated('per_page', 15))->withQueryString();
    }

    private function searchAcademic(Builder $query, IndexAcademicRequest $request, array $columns): Builder
    {
        $search = $request->validated('search');
        return $query->when($search !== null && $search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $column) $query->orWhereRaw("strpos(lower({$column}), lower(?)) > 0", [$search]);
        }));
    }
}
