# 🗄️ Database PDO Wrapper

A modern, lightweight singleton PDO wrapper class for efficient and secure database operations in PHP applications.

## ✨ Features

- **Singleton Pattern**: Ensures single database connection instance across the application
- **Environment Configuration**: Automatic MySQL connection using `.env` configuration
- **Fluent Query Builder**: Chainable methods with `query()`, `bind()`, and `execute()`
- **Comprehensive CRUD Operations**: Built-in methods for all database operations
- **Result Handling**: Multiple result retrieval methods (`resultSet()`, `single()`, `fetchColumn()`)
- **Metadata Access**: Row counting and last insert ID retrieval
- **Transaction Support**: Full transaction management with rollback capabilities
- **Quick Methods**: Shortcut methods for rapid query execution
- **Parameter Binding**: Secure prepared statement parameter binding
- **Error Handling**: Built-in exception handling and error management

## 🚀 Quick Start

### Basic Connection
```php
$db = Database::getInstance();
```

## 📖 API Reference

### Core Methods

#### `query(string $sql): Database`
Prepares an SQL statement for execution.

#### `bind(string $param, mixed $value, int $type = null): Database`
Binds a parameter to the prepared statement.

#### `execute(): bool`
Executes the prepared statement.

#### `resultSet(): array`
Returns all results as an associative array.

#### `single(): array|false`
Returns a single result row.

#### `fetchColumn(int $column = 0): mixed`
Returns a single column from the next row.

#### `rowCount(): int`
Returns the number of affected rows.

#### `lastInsertId(): string`
Returns the ID of the last inserted row.

### Transaction Methods

#### `beginTransaction(): bool`
Initiates a database transaction.

#### `commit(): bool`
Commits the current transaction.

#### `rollBack(): bool`
Rolls back the current transaction.

### Quick Methods

#### `run(string $sql, array $params = []): PDOStatement`
Executes a query with optional parameters.

#### `fetch(string $sql, array $params = []): array|false`
Executes a query and returns a single row.

#### `fetchAll(string $sql, array $params = []): array`
Executes a query and returns all results.

## 💡 Usage Examples

### SELECT Operations

#### Fetch All Records
```php
// Using query builder
$users = $db->query('SELECT * FROM users WHERE status = :status')
            ->bind(':status', 'active')
            ->resultSet();

// Using quick method
$users = $db->fetchAll('SELECT * FROM users WHERE status = ?', ['active']);
```

#### Fetch Single Record
```php
// Using query builder
$user = $db->query('SELECT * FROM users WHERE id = :id')
           ->bind(':id', 1)
           ->single();

// Using quick method
$user = $db->fetch('SELECT * FROM users WHERE id = ?', [1]);
```

#### Fetch Specific Column
```php
$userCount = $db->query('SELECT COUNT(*) FROM users')
                ->fetchColumn();
```

### INSERT Operations

#### Basic Insert
```php
$db->query('INSERT INTO users (name, email, created_at) VALUES (:name, :email, :created_at)')
   ->bind(':name', 'John Doe')
   ->bind(':email', 'john@example.com')
   ->bind(':created_at', date('Y-m-d H:i:s'))
   ->execute();

$userId = $db->lastInsertId();
```

#### Quick Insert
```php
$db->run('INSERT INTO users (name, email) VALUES (?, ?)', [
    'Jane Smith',
    'jane@example.com'
]);
```

### UPDATE Operations

```php
$affected = $db->query('UPDATE users SET email = :email WHERE id = :id')
              ->bind(':email', 'newemail@example.com')
              ->bind(':id', 1)
              ->execute();

$rowsUpdated = $db->rowCount();
```

### DELETE Operations

```php
$db->query('DELETE FROM users WHERE created_at < :date')
   ->bind(':date', '2023-01-01')
   ->execute();

$deletedCount = $db->rowCount();
```

### Transaction Management

#### Basic Transaction
```php
$db->beginTransaction();

try {
    // Transfer money between accounts
    $db->run('UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    $db->run('UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
    
    // Log the transaction
    $db->run('INSERT INTO transactions (from_account, to_account, amount) VALUES (?, ?, ?)', [
        1, 2, 100
    ]);
    
    $db->commit();
    echo "Transaction completed successfully";
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Transaction failed: " . $e->getMessage());
    throw $e;
}
```

#### Complex Transaction with Validation
```php
$db->beginTransaction();

try {
    // Check account balance first
    $balance = $db->fetch('SELECT balance FROM accounts WHERE id = ?', [1]);
    
    if ($balance['balance'] < 100) {
        throw new Exception('Insufficient funds');
    }
    
    // Proceed with transaction
    $db->run('UPDATE accounts SET balance = balance - 100 WHERE id = 1');
    $db->run('UPDATE accounts SET balance = balance + 100 WHERE id = 2');
    
    $db->commit();
    
} catch (Exception $e) {
    $db->rollBack();
    throw new Exception('Transaction failed: ' . $e->getMessage());
}
```

## 🔧 Advanced Usage

### Dynamic Query Building
```php
$conditions = [];
$params = [];

if (!empty($name)) {
    $conditions[] = 'name LIKE ?';
    $params[] = "%$name%";
}

if (!empty($email)) {
    $conditions[] = 'email = ?';
    $params[] = $email;
}

$sql = 'SELECT * FROM users';
if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$users = $db->fetchAll($sql, $params);
```

### Batch Operations
```php
$db->beginTransaction();

try {
    foreach ($logEntries as $entry) {
        $db->run('INSERT INTO logs (user_id, action, timestamp) VALUES (?, ?, ?)', [
            $entry['user_id'],
            $entry['action'],
            $entry['timestamp']
        ]);
    }
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

## 🛡️ Security Best Practices

1. **Always use parameter binding** to prevent SQL injection
2. **Use transactions** for operations that must complete atomically
3. **Handle exceptions** properly and log errors appropriately
4. **Validate input data** before database operations
5. **Use environment variables** for database credentials

## 🎯 Performance Tips

- Use `single()` instead of `resultSet()[0]` for single row queries
- Implement proper indexing on frequently queried columns
- Use `fetchColumn()` for count queries and single value retrievals
- Consider connection pooling for high-traffic applications
- Use transactions judiciously to avoid long-running locks

## 🔍 Error Handling

The Database class throws PDO exceptions. Always wrap database operations in try-catch blocks:

```php
try {
    $user = $db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) {
        throw new Exception('User not found');
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    throw new Exception('Database operation failed');
} catch (Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    throw $e;
}
```