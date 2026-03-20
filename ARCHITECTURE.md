# Architecture: doctrine/dbal

## Purpose

Doctrine DBAL (Database Abstraction Layer) provides a thin but powerful PHP abstraction over multiple database vendors (MySQL/MariaDB, PostgreSQL, SQLite, SQL Server, Oracle, IBM DB2). It offers connection management, query building, schema introspection/diffing, type conversion, and prepared statement caching on top of PDO and native database extensions.

## Directory Structure

```
src/
  Connection.php                  — Central entry point; wraps a driver connection, manages transactions
  Driver_Manager.php              — Static factory that resolves a driver string to a Connection instance
  Configuration.php               — Connection-level settings (cache, middlewares, auto-commit, schema filter)
  Driver.php                      — Driver interface implemented by each backend
  Statement.php                   — Prepared statement wrapper
  Result.php                      — Cursor over a query result set
  Query.php                       — Immutable SQL + params + types value object
  Parameter_Type.php              — Enum: NULL | INTEGER | STRING | LARGE_OBJECT | BOOLEAN | BINARY | ASCII
  Array_Parameter_Type.php        — Enum for expanding IN-list parameters
  Lock_Mode.php                   — Enum: NONE | OPTIMISTIC | PESSIMISTIC_READ | PESSIMISTIC_WRITE
  Transaction_Isolation_Level.php — Enum: READ_UNCOMMITTED | READ_COMMITTED | REPEATABLE_READ | SERIALIZABLE
  Column_Case.php                 — Enum controlling column name casing
  Exception/                      — Domain exception hierarchy (DriverException, ConnectionException, …)
  Driver/                         — Per-vendor driver implementations (PDO\MySQL, Mysqli, PgSQL, SQLite3, …)
  Platforms/                      — SQL dialect classes (AbstractPlatform + vendor subclasses)
  Schema/                         — Schema modelling: Table, Column, Index, ForeignKey, AbstractSchemaManager
  Types/                          — PHP<->SQL type converters (Type registry)
  Query/                          — QueryBuilder + ExpressionBuilder
  SQL/                            — SQL parser used for array-parameter expansion
  Cache/                          — QueryCacheProfile, ArrayResult
  Logging/                        — Middleware-based SQL logging
  Portability/                    — Middleware that normalises result column casing/trimming across drivers
  ArrayParameters/                — IN-list expansion utilities
  Tools/                          — Schema console helpers
  Connection/                     — Connection resolver (StaticServerVersionProvider, …)
  Connections/                    — Primary/replica connection wrapper

tests/
```

## Key Design Decisions

1. **Middleware pipeline.** `Configuration::setMiddlewares()` accepts a stack of `Middleware` decorators that wrap the driver connection. This enables transparent logging, retry, caching, and security auditing without subclassing `Connection`.

2. **Enums for constants.** `ParameterType`, `LockMode`, `TransactionIsolationLevel`, `ArrayParameterType`, and `ColumnCase` are pure PHP 8.1 enums, replacing historical integer/string constants with exhaustive, type-safe values.

3. **Driver abstraction through interfaces.** `Driver`, `Driver\Connection`, `Driver\Statement`, and `Driver\Result` form a minimal interface set. Each vendor backend only implements these four interfaces; all higher-level features live in DBAL itself.

4. **Schema diff engine.** `AbstractSchemaManager::createComparator()` + `SchemaComparator` can diff two `Schema` objects and produce `SchemaDiff` which is then rendered into platform-specific DDL, enabling safe incremental schema migrations.

5. **`#[SensitiveParameter]` on credentials.** Constructor parameters containing passwords are annotated so stack traces never leak credentials.

## Extension Points

- Implement `Driver` + `Driver\Connection` to add a new database backend.
- Register a custom `Middleware` to intercept every query (e.g. query logging, read/write splitting).
- Extend `AbstractPlatform` to support a non-standard SQL dialect.
- Implement `Type` and call `Type::addType()` to add custom PHP<->SQL type conversions.
- Implement `SchemaManagerFactory` to override how the schema manager is instantiated.

## Dependency Flow

```
Application
  └──> DriverManager::getConnection(params)
         └──> Connection  (wraps Driver\Connection via Middleware pipeline)
                ├──> Platform    (SQL dialect)
                ├──> SchemaManager  (introspects / diffs DB schema)
                ├──> QueryBuilder   (builds SQL programmatically)
                └──> Type registry  (PHP<->SQL value conversion)
```

## Performance Notes

- Lazy connecting: the underlying driver connection is opened only when first needed.
- `QueryCacheProfile` caches result sets in a PSR-6 cache pool, keyed on SQL + params.
- Array-parameter expansion (`IN (?, ?, ?)`) is handled by the SQL parser in a single regex pass — avoid very large IN lists.
- Schema introspection queries can be expensive; cache `AbstractSchemaManager` results outside hot paths.
