# 🚀 FANY CLI Documentation

**FANY CLI Console v3.1.0** - Powerful command-line interface for Sollo Framework

---

## 📖 Table of Contents

1. [Installation & Setup](#installation--setup)
2. [Basic Usage](#basic-usage)
3. [Make Commands](#make-commands)
4. [Server Commands](#server-commands)
5. [Database Commands](#database-commands)
6. [Maintenance Commands](#maintenance-commands)
7. [Help & Info](#help--info)
8. [Examples](#examples)
9. [Troubleshooting](#troubleshooting)

---

## Installation & Setup

### Prerequisites

- PHP >= 8.3
- Composer (recommended)
- Sollo Framework installed

### Verify Installation

```bash
php fany --version
```

Expected output:
```
🚀 FANY CLI Console
Version 3.1.0
PHP 8.3.x
```

---

## Basic Usage

### Command Syntax

```bash
php fany <command> [options] [arguments]
```

### Get Help

```bash
php fany help
php fany --help
php fany -h
```

### List All Commands

```bash
php fany list
```

---

## Make Commands

### Create Controller

**Command:**
```bash
php fany make:controller <name> [--model] [--resource]
```

**Parameters:**
- `<name>` - Controller name (e.g., UserController, user)
- `--model` - Generate associated model
- `--resource` - Generate resource controller with CRUD methods

**Examples:**
```bash
# Basic controller
php fany make:controller UserController

# With model
php fany make:controller ProductController --model

# Resource controller
php fany make:controller PostController --resource

# With both
php fany make:controller CommentController --model --resource
```

**Output:**
- Creates file: `app/Controllers/UserController.php`
- If `--model`: Creates `app/Models/User.php`

---

### Create Model

**Command:**
```bash
php fany make:model <name>
```

**Parameters:**
- `<name>` - Model name (e.g., User, Post, Category)

**Examples:**
```bash
php fany make:model User
php fany make:model BlogPost
php fany make:model Category
```

**Output:**
- Creates file: `app/Models/User.php`
- Auto-generates table name (User → users)

---

### Create View

**Command:**
```bash
php fany make:view <name>
```

**Parameters:**
- `<name>` - View path using dot notation (e.g., user.index, posts.show)

**Examples:**
```bash
# Single file
php fany make:view home

# Nested view
php fany make:view users.index
php fany make:view users.show
php fany make:view admin.posts.create
```

**Output:**
- Creates file: `resources/Views/users/index.nixs.php`
- Extension: `.nixs.php` (Nixs template engine)

---

### Create Middleware

**Command:**
```bash
php fany make:middleware <name>
```

**Parameters:**
- `<name>` - Middleware name (e.g., AuthMiddleware, CheckRole)

**Examples:**
```bash
php fany make:middleware AuthMiddleware
php fany make:middleware AdminMiddleware
php fany make:middleware RateLimitMiddleware
```

**Output:**
- Creates file: `app/Middleware/AuthMiddleware.php`

---

### Create Route

**Command:**
```bash
php fany make:route <name> [--G] [--P] [--U] [--D] [--RESOURCE] [--M=<middleware>]
```

**Parameters:**
- `<name>` - Route name/path
- `--G` - Add GET route
- `--P` - Add POST route
- `--U` - Add PUT route
- `--D` - Add DELETE route
- `--RESOURCE` - Add all CRUD routes
- `--M=<middleware>` - Attach middleware

**Examples:**
```bash
# Single route
php fany make:route users --G

# Multiple methods
php fany make:route posts --G --P --U --D

# Resource routes
php fany make:route articles --RESOURCE

# With middleware
php fany make:route admin/dashboard --G --M=AuthMiddleware
```

**Output:**
- Appends to: `app/Routes/web.php`

---

### Create Migration

**Command:**
```bash
php fany make:migration <name> [--table=<name>]
```

**Parameters:**
- `<name>` - Migration name (descriptive)
- `--table=<name>` - Table name (optional)

**Examples:**
```bash
# Create table
php fany make:migration create_users_table --table=users

# Add column
php fany make:migration add_email_to_users

# Modify table
php fany make:migration alter_posts_table --table=posts
```

**Output:**
- Creates file: `app/database/migrations/2024_01_15_120000_create_users_table.php`
- Timestamp: Auto-generated based on current time

---

### Create Seeder

**Command:**
```bash
php fany make:seeder <name>
```

**Parameters:**
- `<name>` - Seeder name (e.g., UserSeeder, PostSeeder)

**Examples:**
```bash
php fany make:seeder UserSeeder
php fany make:seeder CategorySeeder
php fany make:seeder DatabaseSeeder
```

**Output:**
- Creates file: `app/database/seeders/UserSeeder.php`

---

### Create Config

**Command:**
```bash
php fany make:config <name>
```

**Parameters:**
- `<name>` - Config name (e.g., database, cache, mail)

**Examples:**
```bash
php fany make:config database
php fany make:config cache
php fany make:config mail
```

**Output:**
- Creates file: `config/database.php`

---

### Create Nixs Templates

**Command:**
```bash
php fany make:nixs <name> [--type=<type>] [--layout=<layout>] [--bootstrap] [--tailwind] [--alpine]
```

**Parameters:**
- `<name>` - Template name/path
- `--type` - Template type (page, layout, partial, form, crud, api)
- `--layout` - Extend layout
- `--bootstrap` - Include Bootstrap CSS
- `--tailwind` - Include Tailwind CSS
- `--alpine` - Include Alpine.js

**Template Types:**
- `page` - Basic page template (default)
- `layout` - Layout template with sections
- `partial` - Reusable partial
- `form` - Form with CSRF
- `crud` - Full CRUD templates
- `api` - API response template

**Examples:**
```bash
# Basic page
php fany make:nixs home

# Nested pages
php fany make:nixs users.index
php fany make:nixs users.show

# Layout
php fany make:nixs layouts.app --type=layout --bootstrap

# Form
php fany make:nixs posts.create --type=form --tailwind

# CRUD templates
php fany make:nixs articles --type=crud
```

**Output:**
- Single file: `resources/Views/home.nixs.php`
- Nested: `resources/Views/users/index.nixs.php`
- CRUD: Creates 4 files (index, create, edit, show)

---

### Create Environment File

**Command:**
```bash
php fany make:env [options]
```

**Options:**
- `-i, --interactive` - Interactive setup wizard
- `--name=<name>` - Application name
- `--env=<env>` - Environment (local, dev, staging, prod, test)
- `--database=<type>` - Database type (mysql, pgsql, sqlite, sqlsrv)
- `--force` - Overwrite existing .env
- `--with-example` - Generate .env.example

**Examples:**
```bash
# Interactive mode (RECOMMENDED)
php fany make:env -i

# Quick setup
php fany make:env --name="My App"

# Production
php fany make:env --name="My App" --env=prod --force

# With example file
php fany make:env --name="My App" --with-example
```

**Output:**
- Creates: `.env`
- If `--with-example`: Creates `.env.example`

---

### Create Component

**Command:**
```bash
php fany make:component <name> [--props=<props>] [--slots] [--alpine] [--class]
```

**Parameters:**
- `<name>` - Component name
- `--props=<props>` - Component properties (comma-separated)
- `--slots` - Include slot support
- `--alpine` - Include Alpine.js
- `--class` - Create component class

**Examples:**
```bash
# Basic component
php fany make:component Button

# With props
php fany make:component Card --props=title,content,color

# With slots
php fany make:component Modal --slots
```

**Output:**
- Creates: `resources/Views/components/Button.nixs.php`
- If `--class`: Creates `app/View/Components/Button.php`

---

## Server Commands

### Start Development Server

**Command:**
```bash
php fany server [--port=<port>] [--host=<host>]
```

**Parameters:**
- `--port` - Port number (default: 8000)
- `--host` - Host address (default: localhost)

**Examples:**
```bash
# Default (localhost:8000)
php fany server

# Custom port
php fany server --port=3000

# Public access
php fany server --host=0.0.0.0

# Full custom
php fany server --port=8080 --host=0.0.0.0
```

**Output:**
```
🚀 FANY Development Server
🔡 Server running at: http://localhost:8000
🔒 Document root: /path/to/project/public
⏰ Started at: 2024-01-15 10:30:45
🌐 Press Ctrl+C to stop the server
```

**Alias:**
```bash
php fany serve          # Same as server
```

---

## Database Commands

### Run Migrations

**Command:**
```bash
php fany db:migrate [--force]
```

**Parameters:**
- `--force` - Continue on errors

**Example:**
```bash
php fany db:migrate
```

**Output:**
```
🗃️  Running database migrations...
────────────────────────────────────
✓ Migrated: 2024_01_15_120000_create_users_table
✓ Migrated: 2024_01_15_120100_create_posts_table
────────────────────────────────────
✅ Migration completed! (2 migrations)
```

---

### Rollback Migrations

**Command:**
```bash
php fany db:rollback [--step=<n>]
```

**Parameters:**
- `--step=<n>` - Number of batches to rollback (default: 1)

**Examples:**
```bash
# Last batch
php fany db:rollback

# Last 2 batches
php fany db:rollback --step=2

# Rollback all
php fany db:rollback --step=999
```

**Output:**
```
🔄 Rolling back database migrations...
────────────────────────────────────
✓ Rolled back: 2024_01_15_120100_create_posts_table
✓ Rolled back: 2024_01_15_120000_create_users_table
────────────────────────────────────
✅ Rollback completed! (2 migrations)
```

---

### Run Seeders

**Command:**
```bash
php fany db:seed [--class=<name>]
```

**Parameters:**
- `--class=<name>` - Specific seeder to run

**Examples:**
```bash
# Run DatabaseSeeder
php fany db:seed

# Run specific seeder
php fany db:seed --class=UserSeeder
php fany db:seed --class=CategorySeeder
```

**Output:**
```
🌱 Running database seeders...
────────────────────────────────────
Running UserSeeder...
✓ UserSeeder completed
────────────────────────────────────
✅ Database seeding completed!
```

---

### Show Migration Status

**Command:**
```bash
php fany db:status
```

**Example:**
```bash
php fany db:status
```

**Output:**
```
🔍 Migration Status

Migration                                    Batch      Status
─────────────────────────────────────────────────────────────
2024_01_15_120000_create_users_table         1          ✅ Ran
2024_01_15_120100_create_posts_table         1          ✅ Ran
2024_01_15_120200_create_comments_table      -          ⏳ Pending

Summary:
Total migrations: 3
Ran: 2
Pending: 1

💡 Run php fany db:migrate to execute pending migrations.
```

---

### Reset Database

**Command:**
```bash
php fany db:reset [--seed]
```

**Parameters:**
- `--seed` - Run seeders after migration

**Example:**
```bash
# Reset only
php fany db:reset

# Reset and seed
php fany db:reset --seed
```

**Warning:** ⚠️ This will DROP ALL TABLES

---

### Fresh Database

**Command:**
```bash
php fany db:fresh [--seed]
```

**Parameters:**
- `--seed` - Run seeders after migration

**Example:**
```bash
php fany db:fresh
php fany db:fresh --seed
```

**Output:** Same as `db:reset`

---

## Maintenance Commands

### Down (Maintenance Mode)

**Command:**

```bash
php fany down
```

### Custom View

If you want to customize the maintenance page, you can do so by editing the HTML inside:

```text
core/Console/Commands/stubs/maintenance.stub
```

To regenerate or refresh the maintenance file while keeping your custom HTML, run:

```bash
php fany down --force
```

### Up (Resume Service)

**Command:**

```bash
php fany up
```

---

### Backup (sql & gz)

**Command:**
```bash
php fany db:backup
```

**Example:**
```bash
# Example 1: Structure Backup
php fany db:backup --type=structure

#Example 2: Data Backup
php fany db:backup --type=data

#Example 3: Compressed Backup
php fany db:backup --compress

#Example 4: Custom Path
php fany db:backup --path=my-backups

#Example 5: All Options Combined
php fany db:backup --type=structure --path=backups/test --compress
```

---

## 🆘 Help & Info

### Show Help

```bash
php fany help
php fany --help
php fany -h
```

### Show Version

```bash
php fany --version
php fany -v
```

### List Commands

```bash
php fany list
```

---

## 📚 Examples

### Command Quick Reference

```bash
# Controllers
php fany make:controller UserController
php fany make:controller UserController --resource --model

# Models
php fany make:model User

# Views
php fany make:view users.index
php fany make:view users.show

# Middleware
php fany make:middleware AuthMiddleware

# Routes
php fany make:route users --RESOURCE

# Migrations
php fany make:migration create_users_table --table=users

# Seeders
php fany make:seeder UserSeeder

# Configuration
php fany make:config database

# Templates
php fany make:nixs home
php fany make:nixs layouts.app --type=layout

# Server
php fany server --port=3000

# Database
php fany db:migrate
php fany db:seed
php fany db:status
php fany db:reset --seed

# Environment
php fany make:env -i

# Info
php fany help
php fany list
php fany --version
```

---

## 📞 Support

- **Documentation:** https://sollo.xo.je/fany

---

**Last Updated:** January 2024 | FANY CLI v2.0