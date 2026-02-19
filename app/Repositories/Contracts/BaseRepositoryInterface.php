<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Criteria;

interface BaseRepositoryInterface
{
    /**
     * Find model by ID
     */
    public function find(int|string $id): ?Model;

    /**
     * Find model by UUID
     */
    public function findByUuid(string $uuid): ?Model;

    /**
     * Find model by ID or fail
     */
    public function findOrFail(int|string $id): Model;

    /**
     * Find model by UUID or fail
     */
    public function findByUuidOrFail(string $uuid): Model;

    /**
     * Get all records
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Create new record
     */
    public function create(array $attributes): Model;

    /**
     * Update existing record
     */
    public function update(int|string $id, array $attributes): Model;

    /**
     * Delete record
     */
    public function delete(int|string $id): bool;

    /**
     * Get paginated results
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Eager load relationships
     */
    public function with(array|string $relations): self;

    /**
     * Order results by column
     */
    public function orderBy(string $column, string $direction = 'asc'): self;

    /**
     * Add where clause
     */
    public function where(string $column, mixed $value, string $operator = '='): self;

    /**
     * Add whereIn clause
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Push criteria for complex queries
     */
    public function pushCriteria(Criteria $criteria): self;

    /**
     * Apply all criteria
     */
    public function applyCriteria(): self;

    /**
     * Reset criteria
     */
    public function resetCriteria(): self;

    /**
     * Skip criteria application
     */
    public function skipCriteria(bool $skip = true): self;

    /**
     * Find or create record
     */
    public function firstOrCreate(array $attributes, array $values = []): Model;

    /**
     * Update or create record
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * Check if record exists
     */
    public function exists(array $conditions): bool;

    /**
     * Count records
     */
    public function count(array $conditions = []): int;
}
