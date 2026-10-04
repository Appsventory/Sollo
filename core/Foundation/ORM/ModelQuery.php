<?php

namespace Core\Foundation\ORM;

use Core\Foundation\Database\QueryBuilder;

/**
 * QueryBuilder that returns Model instances. Created by Model::query(); every
 * static call that Model does not define is forwarded here, so this works:
 *
 *     User::where('active', 1)->orderBy('name')->limit(10)->get();
 *     User::where('email', $email)->first();
 *     User::count();
 */
class ModelQuery extends QueryBuilder
{
    /** @var class-string<Model> */
    protected string $modelClass;
    protected bool $softDeletes;
    protected bool $withTrashed = false;
    protected int $modelPerPage;
    protected string $primaryKey;

    public function __construct(string $modelClass)
    {
        /** @var Model $model */
        $model = new $modelClass();

        $this->modelClass = $modelClass;
        $this->softDeletes = $model->usesSoftDeletes();
        $this->modelPerPage = $model->getPerPage();
        $this->primaryKey = $model->getKeyName();

        parent::__construct($model->getTable());
    }

    /**
     * Include soft-deleted rows.
     */
    public function withTrashed(): static
    {
        $this->withTrashed = true;
        return $this;
    }

    public function find($id)
    {
        return (clone $this)->where($this->primaryKey, $id)->first();
    }

    public function findOrFail($id)
    {
        return $this->find($id) ?? throw new ModelNotFoundException($this->modelClass, $id);
    }

    public function firstOrFail()
    {
        return $this->first() ?? throw new ModelNotFoundException($this->modelClass);
    }

    public function all()
    {
        return $this->get();
    }

    /**
     * Rows as Model instances.
     */
    public function get()
    {
        return array_map(
            fn(array $row) => ($this->modelClass)::hydrate($row),
            parent::get()
        );
    }

    public function paginate($perPage = null, $page = null)
    {
        return parent::paginate($perPage ?? $this->modelPerPage, $page);
    }

    /**
     * With soft deletes, delete() marks the rows as deleted.
     */
    public function delete()
    {
        if ($this->softDeletes) {
            return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return parent::delete();
    }

    /**
     * (user conditions) AND deleted_at IS NULL: grouped, so orWhere cannot leak deleted rows.
     */
    protected function compileWhere(): string
    {
        $where = parent::compileWhere();

        if (!$this->softDeletes || $this->withTrashed) {
            return $where;
        }

        return $where === '' ? 'deleted_at IS NULL' : "({$where}) AND deleted_at IS NULL";
    }
}
