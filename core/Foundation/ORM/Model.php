<?php

namespace Core\Foundation\ORM;

use Core\Foundation\Database\Database;
use Core\Foundation\Database\QueryBuilder;

/**
 * Base Model.
 *
 * Mass assignment (constructor, create(), fill(), update([...])) only accepts
 * columns listed in $fillable. If $fillable is empty, $guarded decides:
 * the default ['*'] blocks everything, so define $fillable (or set
 * $guarded = [] / a list of protected columns) on your model.
 *
 * Static calls that are not defined here are forwarded to a ModelQuery:
 * User::where(...)->get(), User::count(), User::orderBy(...)->first().
 */
abstract class Model implements \JsonSerializable
{
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $hidden = [];
    protected $casts = [];
    protected $timestamps = true;
    protected $softDeletes = false;
    protected $perPage = 15;

    protected $attributes = [];
    protected $original = [];
    protected $relations = [];
    protected $exists = false;

    public function __construct(array $attributes = [])
    {
        if ($attributes) {
            $this->fill($attributes);
        }
        $this->syncOriginal();
    }

    /**
     * Build a model from a database row (no mass-assignment filtering).
     */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->exists = true;
        $model->syncOriginal();

        return $model;
    }

    /**
     * Start a query that returns models of this class.
     */
    public static function query(): ModelQuery
    {
        return new ModelQuery(static::class);
    }

    public static function __callStatic($method, $arguments)
    {
        if (!method_exists(ModelQuery::class, $method)) {
            throw new \BadMethodCallException(static::class . "::{$method}() does not exist.");
        }

        return static::query()->$method(...$arguments);
    }

    // ------------------------------------------------------------------
    // Metadata
    // ------------------------------------------------------------------

    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        // users for User, blog_posts for BlogPost
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($className))) . 's';
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey()
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getPerPage(): int
    {
        return (int) $this->perPage;
    }

    public function usesSoftDeletes(): bool
    {
        return (bool) $this->softDeletes;
    }

    // ------------------------------------------------------------------
    // Attributes
    // ------------------------------------------------------------------

    /**
     * Mass-assign attributes. Columns that are not fillable are ignored.
     */
    public function fill(array $attributes)
    {
        if (!$this->fillable && $this->guarded === ['*'] && $attributes) {
            throw new \LogicException(
                static::class . ' has no mass-assignable columns. Define protected $fillable = [...] '
                . '(or protected $guarded = [] to allow every column).'
            );
        }

        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    public function isFillable($key)
    {
        if (!is_string($key) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            return false;
        }

        if ($this->fillable) {
            return in_array($key, $this->fillable, true);
        }

        if ($this->guarded === ['*']) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    public function setAttribute($key, $value)
    {
        $mutator = 'set' . self::studly($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
        } elseif (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        } else {
            $value = null;
        }

        $accessor = 'get' . self::studly($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($value);
        }

        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return is_string($value) ? json_decode($value) : $value;
            case 'array':
            case 'json':
            case 'collection':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'date':
            case 'datetime':
                return $value instanceof \DateTimeInterface ? $value : new \DateTime($value);
            case 'timestamp':
                return is_numeric($value) ? (int) $value : strtotime($value);
            default:
                return $value;
        }
    }

    /**
     * Attribute values as they are written to the database (arrays/objects
     * of json casts are encoded, dates are formatted).
     */
    protected function storageValue($key, $value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        return $value;
    }

    public function syncOriginal()
    {
        $this->original = $this->attributes;
        return $this;
    }

    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return !empty($dirty);
        }

        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    protected function newBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->getTable());
    }

    public function save()
    {
        return $this->exists ? $this->update() : $this->insert();
    }

    protected function insert()
    {
        $now = date('Y-m-d H:i:s');

        if ($this->timestamps) {
            $this->attributes['created_at'] ??= $now;
            $this->attributes['updated_at'] ??= $now;
        }

        $values = [];
        foreach ($this->attributes as $key => $value) {
            $values[$key] = $this->storageValue($key, $value);
        }

        $id = $this->newBuilder()->insertGetId($values);

        if (!isset($this->attributes[$this->primaryKey]) && $id !== false && $id !== '' && $id !== '0') {
            $this->attributes[$this->primaryKey] = is_numeric($id) ? $id + 0 : $id;
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    /**
     * Update the row. Optionally fill (mass-assign) attributes first.
     */
    public function update($attributes = [])
    {
        if ($attributes) {
            $this->fill($attributes);
        }

        $dirty = $this->getDirty();

        if (!$dirty) {
            return true;
        }

        if ($this->timestamps && !isset($dirty['updated_at'])) {
            $dirty['updated_at'] = $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $values = [];
        foreach ($dirty as $key => $value) {
            $values[$key] = $this->storageValue($key, $value);
        }

        $this->newBuilder()->where($this->primaryKey, $this->getKey())->update($values);
        $this->syncOriginal();

        return true;
    }

    public function delete()
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->softDeletes) {
            $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
            return $this->update();
        }

        $this->newBuilder()->where($this->primaryKey, $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    public static function create(array $attributes = [])
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function find($id)
    {
        return static::query()->find($id);
    }

    public static function findOrFail($id)
    {
        return static::query()->findOrFail($id);
    }

    public static function all()
    {
        return static::query()->get();
    }

    public static function beginTransaction()
    {
        return Database::getInstance()->beginTransaction();
    }

    public static function commit()
    {
        return Database::getInstance()->commit();
    }

    public static function rollback()
    {
        return Database::getInstance()->rollback();
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray()
    {
        $array = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $value = $this->getAttribute($key);
                $array[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
            }
        }

        foreach ($this->relations as $key => $value) {
            $array[$key] = $value;
        }

        return $array;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson()
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE);
    }

    private static function studly($value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', (string) $value)));
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public function __toString()
    {
        return $this->toJson();
    }
}
