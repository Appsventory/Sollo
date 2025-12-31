<?php

namespace Core\Foundation\ORM;

use Core\Foundation\Database\Database;

abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['id'];
    protected $hidden = [];
    protected $casts = [];
    protected $dates = ['created_at', 'updated_at'];
    protected $timestamps = true;
    protected $softDeletes = false;
    protected $perPage = 15;

    protected $attributes = [];
    protected $original = [];
    protected $relations = [];
    protected $exists = false;

    public function __construct(array $attributes = [])
    {
        // Check if this is from DB query (has 'exists' property set later) or mass-assignment
        // For now, just fill all attributes - we'll differentiate later if needed
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        $this->syncOriginal();
    }

    /**
     * Get database instance
     */
    protected static function db(): Database
    {
        return Database::getInstance();
    }

    /**
     * Get table name
     */
    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        // Auto-generate table name from class name
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($className))) . 's';
    }

    /**
     * Fill model with attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Set attribute value
     */
    public function setAttribute($key, $value)
    {
        // Apply mutators
        $mutator = 'set' . studly_case($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get attribute value
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
        } elseif (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        } else {
            $value = null;
        }

        // Apply accessors
        $accessor = 'get' . studly_case($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($value);
        }

        // Apply casts
        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Cast attribute to specified type
     */
    protected function castAttribute($key, $value)
    {
        $castType = $this->casts[$key];

        if (is_null($value)) {
            return $value;
        }

        switch ($castType) {
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
                return json_decode($value);
            case 'array':
            case 'json':
                return json_decode($value, true);
            case 'collection':
                return json_decode($value, true);
            case 'date':
            case 'datetime':
                return new \DateTime($value);
            case 'timestamp':
                return strtotime($value);
            default:
                return $value;
        }
    }

    /**
     * Check if attribute is fillable
     */
    public function isFillable($key)
    {
        // If fillable is defined, only allow those
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        // If guarded is defined, allow everything except those
        return !in_array($key, $this->guarded);
    }

    /**
     * Sync original attributes
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Check if model is dirty (has changes)
     */
    public function isDirty($attributes = null)
    {
        if (is_null($attributes)) {
            return !empty($this->getDirty());
        }

        $dirty = $this->getDirty();
        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get dirty attributes
     */
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

    /**
     * Save model to database
     */
    public function save()
    {
        if ($this->exists) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new record
     */
    protected function insert()
    {
        $attributes = $this->attributes;

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
        }

        $sql = "INSERT INTO {$this->getTable()} (" . implode(',', array_keys($attributes)) . ") VALUES (" . str_repeat('?,', count($attributes) - 1) . "?)";

        $result = self::query($sql, array_values($attributes)) ? true : false;

        if ($result) {
            $this->attributes[$this->primaryKey] = self::db()->lastInsertId();
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Update existing record
     */
    public function update($attributes = [])
    {
        // If attributes are provided, fill them first
        if (!empty($attributes)) {
            $this->fill($attributes);
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true; // No changes to save
        }

        if ($this->timestamps && !isset($dirty['updated_at'])) {
            $dirty['updated_at'] = date('Y-m-d H:i:s');
        }

        $sets = array_map(function ($key) {
            return "$key = ?";
        }, array_keys($dirty));
        $sql = "UPDATE {$this->getTable()} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";

        $values = array_values($dirty);
        $values[] = $this->attributes[$this->primaryKey];

        $result = self::db()->execute($sql, $values);

        if ($result) {
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Delete model from database
     */
    public function delete()
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->softDeletes) {
            $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
            return $this->save();
        } else {
            $sql = "DELETE FROM {$this->getTable()} WHERE {$this->primaryKey} = ?";
            return self::db()->execute($sql, [$this->attributes[$this->primaryKey]]);
        }
    }

    /**
     * Create new model instance
     */
    public static function create(array $attributes = [])
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Find model by primary key
     */
    public static function find($id)
    {
        $instance = new static;
        $sql = "SELECT * FROM {$instance->getTable()} WHERE {$instance->primaryKey} = ?";
        $result = self::db()->select($sql, [$id]);

        if (!empty($result)) {
            $model = new static($result[0]);
            $model->exists = true;
            $model->syncOriginal();
            return $model;
        }

        return null;
    }

    /**
     * Find model by primary key or fail
     */
    public static function findOrFail($id)
    {
        $model = static::find($id);

        if (is_null($model)) {
            throw new \Exception("Model not found with ID: {$id}");
        }

        return $model;
    }

    /**
     * Find first model matching conditions
     */
    public static function where($column, $operator = '=', $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        $instance = new static;
        $sql = "SELECT * FROM {$instance->getTable()} WHERE {$column} {$operator} ?";
        $results = self::db()->select($sql, [$value]);

        $models = [];
        foreach ($results as $result) {
            $model = new static($result);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Get first model
     */
    public static function first()
    {
        $instance = new static;
        $sql = "SELECT * FROM {$instance->getTable()} LIMIT 1";
        $result = self::db()->select($sql);

        if (!empty($result)) {
            $model = new static($result[0]);
            $model->exists = true;
            $model->syncOriginal();
            return $model;
        }

        return null;
    }

    /**
     * Get all models
     */
    public static function all()
    {
        $instance = new static;
        $sql = "SELECT * FROM {$instance->getTable()}";

        if ($instance->softDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }

        $results = self::db()->select($sql);

        $models = [];
        foreach ($results as $result) {
            $model = new static($result);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Paginate results
     */
    public static function paginate($page = 1, $perPage = null)
    {
        $instance = new static;
        $perPage = $perPage ?? $instance->perPage;
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countSql = "SELECT COUNT(*) as count FROM {$instance->getTable()}";
        if ($instance->softDeletes) {
            $countSql .= " WHERE deleted_at IS NULL";
        }

        $totalResult = self::db()->select($countSql);
        $total = $totalResult[0]['count'];

        // Get paginated results
        $sql = "SELECT * FROM {$instance->getTable()}";
        if ($instance->softDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $results = self::db()->select($sql);

        $models = [];
        foreach ($results as $result) {
            $model = new static($result);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }

        return [
            'data' => $models,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Raw query execution
     */
    protected static function query($sql, $params = [])
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction()
    {
        return Database::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit()
    {
        return Database::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback()
    {
        return Database::getInstance()->rollback();
    }

    /**
     * Convert model to array
     */
    public function toArray()
    {
        $array = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden)) {
                $array[$key] = $this->getAttribute($key);
            }
        }

        // Include relations
        foreach ($this->relations as $key => $value) {
            $array[$key] = $value;
        }

        return $array;
    }

    /**
     * Convert model to JSON
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic getter
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /**
     * Magic unset
     */
    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * Convert to string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}

// Helper function for studly case
if (!function_exists('studly_case')) {
    function studly_case($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
