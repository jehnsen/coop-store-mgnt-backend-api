<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Criteria\Criteria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;
    protected SupportCollection $criteria;
    protected bool $skipCriteria = false;

    public function __construct()
    {
        $this->model = $this->makeModel();
        $this->criteria = collect();
    }

    /**
     * Specify Model class name
     */
    abstract protected function model(): string;

    /**
     * Make Model instance
     */
    protected function makeModel(): Model
    {
        $model = app($this->model());

        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $model;
    }

    /**
     * Get new query instance (respects global scopes like BelongsToStore)
     */
    protected function newQuery()
    {
        return $this->model->newQuery();
    }

    /**
     * Reset to fresh model instance
     */
    protected function resetModel(): void
    {
        $this->model = $this->makeModel();
    }

    /**
     * Find model by ID
     */
    public function find(int|string $id): ?Model
    {
        $this->applyCriteria();
        $result = $this->newQuery()->find($id);
        $this->resetCriteria();
        return $result;
    }

    /**
     * Find model by UUID
     */
    public function findByUuid(string $uuid): ?Model
    {
        $this->applyCriteria();
        $result = $this->newQuery()->where('uuid', $uuid)->first();
        $this->resetCriteria();
        return $result;
    }

    /**
     * Find model by ID or fail
     */
    public function findOrFail(int|string $id): Model
    {
        $this->applyCriteria();
        $result = $this->newQuery()->findOrFail($id);
        $this->resetCriteria();
        return $result;
    }

    /**
     * Find model by UUID or fail
     */
    public function findByUuidOrFail(string $uuid): Model
    {
        $this->applyCriteria();
        $result = $this->newQuery()->where('uuid', $uuid)->firstOrFail();
        $this->resetCriteria();
        return $result;
    }

    /**
     * Get all records
     */
    public function all(array $columns = ['*']): Collection
    {
        $this->applyCriteria();
        $result = $this->newQuery()->get($columns);
        $this->resetCriteria();
        return $result;
    }

    /**
     * Create new record
     */
    public function create(array $attributes): Model
    {
        return $this->newQuery()->create($attributes);
    }

    /**
     * Update existing record
     */
    public function update(int|string $id, array $attributes): Model
    {
        $model = $this->findOrFail($id);
        $model->update($attributes);
        return $model->fresh();
    }

    /**
     * Delete record
     */
    public function delete(int|string $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    /**
     * Get paginated results
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $this->applyCriteria();
        $result = $this->newQuery()->paginate($perPage, $columns);
        $this->resetCriteria();
        return $result;
    }

    /**
     * Eager load relationships
     */
    public function with(array|string $relations): self
    {
        $this->model = $this->newQuery()->with($relations)->getModel();
        return $this;
    }

    /**
     * Order results by column
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->model = $this->newQuery()->orderBy($column, $direction)->getModel();
        return $this;
    }

    /**
     * Add where clause
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->model = $this->newQuery()->where($column, $operator, $value)->getModel();
        return $this;
    }

    /**
     * Add whereIn clause
     */
    public function whereIn(string $column, array $values): self
    {
        $this->model = $this->newQuery()->whereIn($column, $values)->getModel();
        return $this;
    }

    /**
     * Push criteria for complex queries
     */
    public function pushCriteria(Criteria $criteria): self
    {
        $this->criteria->push($criteria);
        return $this;
    }

    /**
     * Apply all criteria
     */
    public function applyCriteria(): self
    {
        if ($this->skipCriteria === true) {
            return $this;
        }

        foreach ($this->criteria as $criteria) {
            if ($criteria instanceof Criteria) {
                $this->model = $criteria->apply($this->newQuery(), $this)->getModel();
            }
        }

        return $this;
    }

    /**
     * Reset criteria
     */
    public function resetCriteria(): self
    {
        $this->criteria = collect();
        $this->resetModel();
        return $this;
    }

    /**
     * Skip criteria application
     */
    public function skipCriteria(bool $skip = true): self
    {
        $this->skipCriteria = $skip;
        return $this;
    }

    /**
     * Find or create record
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->firstOrCreate($attributes, $values);
    }

    /**
     * Update or create record
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->updateOrCreate($attributes, $values);
    }

    /**
     * Check if record exists
     */
    public function exists(array $conditions): bool
    {
        $query = $this->newQuery();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        return $query->exists();
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        $query = $this->newQuery();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        return $query->count();
    }
}
