# STABILIZATION 0.0.2: Adige ORM

This document defines the `0.0.2` stabilization plan, now focused on the ORM layer.

Scope of this stabilization:
- stabilize `ActiveRecord`, `Connection` and `Schema`
- close the minimum public contracts of the ORM layer
- reduce implicit behavior and unexpected side effects
- cover the ORM layer with automated tests
- preserve the HTTP/kernel/router core already stabilized in `0.0.1`

Out of scope for now:
- large new framework features outside the ORM layer
- advanced migration/seeding integrations
- new HTTP features not required by the ORM

## Current Summary

Estimated overall state:
- approximate progress: `90% to 95%`

Starting points of the ORM:
- [`src/core/database/ActiveRecord.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/ActiveRecord.php)
- [`src/core/database/Connection.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/Connection.php)
- [`src/core/database/Schema.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/Schema.php)
- [`src/core/database/QueryBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/QueryBuilder.php)
- [`src/core/database/DsnBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/DsnBuilder.php)

Remaining backlog moved to:
- [`TODO.md`](/home/mathmpr/PhpstormProjects/adige/TODO.md)

What already changed materially:
- `ActiveRecord` no longer keeps the connection as the main fixed state of the instance
- explicit connections now flow through `one()`, `all()`, `build()`, `execute()`, `save()` and `remove()`
- hydrated models preserve the runtime connection used by the main query
- relations reuse the same runtime connection in eager and lazy loading
- query building was split out of `ActiveRecord`
- DSN building was split out of `Connection`
- `Schema` now dispatches to a MySQL-specific implementation instead of embedding MySQL logic directly in the base class
- SQLite now has its own dialect classes for DSN, schema reading and query building
- the default connection policy is now explicit: the first registered connection may become default via constructor flag, and later promotion must be explicit
- connection names now fail fast when duplicated instead of being silently overwritten

---

## Phase 0: ORM Freeze and Diagnosis

Goal:
- stabilize the ORM direction before adding new behavior

Current status:
- completed

Tasks:
- [x] Define the stabilization target version as `0.0.2`.
- [x] Document the intended public API of the ORM at diagnosis level:
  - [x] `ActiveRecord`
  - [x] `Connection`
  - [x] `Schema`
- [x] Classify what is public API and what is internal ORM detail at diagnosis level.
- [x] List current behaviors known as bug, limitation or coupling.

Notes:
- the current work is already clearly focused on stabilization, not feature expansion
- the connection diagnosis already closed two important ORM policy decisions:
  - default connection selection is no longer treated as an accidental side effect
  - duplicate connection names should raise an explicit exception
- the connection diagnosis also closed the minimum exception policy:
  - `getDefaultConnection()` fails with `DefaultConnectionNotDefinedException`
  - connection creation failures are wrapped as `CantConnectException`
  - duplicate connection names fail with `ConnectionNameAlreadyExistsException`
  - query execution errors bubble as `PDOException`
- the intended public API line for diagnosis is now clear enough:
  - public surface under stabilization: `ActiveRecord`, `Connection`, `Schema`
  - internal implementation detail still free to change: dialect builders/readers and internal query assembly helpers
- the next useful artifact for this phase is an ORM public API document similar to the `0.0.1` core stabilization docs

Acceptance criteria:
- there is a clear line between what applications may use and what is still internal ORM detail

---

## Phase 1: Define the `Connection` Contract

Goal:
- make connection creation, selection and usage predictable

Current status:
- completed

Tasks:
- [x] Review the contract of `Connection::__construct()`.
- [x] Define an explicit contract for the default connection.
- [x] Review `Connection::getDefaultConnection()` and its failure mode.
- [x] Review the connection exception policy.
- [x] Review `autoCommit` and transaction policy.
- [x] Define the expected behavior of:
  - [x] `query()`
  - [x] `select()`
  - [x] `insert()`
  - [x] `update()`
  - [x] `delete()`

Notes:
- `Connection` now has explicit `type` and `name`
- when `name` is empty, it falls back to `host`
- connections are now indexed by `name`, not by `host`
- DSN generation is no longer inlined inside `Connection`
- base DSN abstraction lives in [`src/core/database/DsnBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/DsnBuilder.php)
- MySQL DSN implementation lives in [`src/core/database/dialects/mysql/DsnBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/mysql/DsnBuilder.php)
- SQLite DSN implementation now lives in [`src/core/database/dialects/sqlite/DsnBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/sqlite/DsnBuilder.php)
- unsupported connection types now fail explicitly instead of falling back silently
- the default connection contract is now:
  - the first successfully registered connection may become default via constructor flag
  - later connections do not steal default status implicitly
  - explicit promotion must happen through the connection instance itself
  - duplicate names must fail explicitly instead of overwriting an existing registration
- `getDefaultConnection()` now has a closed failure mode:
  - it returns `Connection` when a default exists
  - it throws `DefaultConnectionNotDefinedException` when no default exists
- the connection exception policy is now explicit:
  - connection bootstrap errors are wrapped in `CantConnectException`
  - duplicate registration errors use `ConnectionNameAlreadyExistsException`
  - missing default connection uses `DefaultConnectionNotDefinedException`
  - query execution errors bubble as `PDOException`
- DML return behavior is now partially explicit:
  - `query()` returns `PDOStatement`
  - `select()` always returns `array`
  - `insert()` returns the `lastInsertId()` on success
  - `update()` returns affected rows
  - `delete()` returns affected rows
- query execution no longer captures `PDOException` internally in the common path; query failures now bubble to the caller
- `autoCommit` is now available both as option and constructor argument:
  - when `true`, the common query path opens a transaction and commits automatically
  - when `false`, the common query path still opens the transaction, but commit remains the developer's responsibility
- explicit developer transactions now override automatic internal commit:
  - internal query execution marks the transaction as internal only when it opened the transaction itself
  - explicit `beginTransaction()` marks the transaction as external
  - `autoCommit` must not close an external transaction opened by the developer
  - `commitTransaction()` and `rollBackTransaction()` clear the transaction caller state
- transaction boundaries are now explicit enough for the `Connection` contract of `0.0.2`

Status note:
- the public `Connection` contract is now explicit enough for `0.0.2`
- dialect selection now supports MySQL and SQLite without silent fallback

Acceptance criteria:
- opening, reusing and failing connections is predictable and documented

---

## Phase 2: Stabilize `Schema`

Goal:
- make schema reading and caching explicit and safe

Current status:
- completed

Tasks:
- [x] Review automatic persistence to `schema.json`.
- [x] Decide whether schema cache will be:
  - [x] file-based
  - [x] in-memory
  - [x] configurable
- [x] Define behavior when schema is missing or outdated.
- [x] Review the contract of:
  - [x] `getSchema()`
  - [x] `pkName()`
  - [x] `getFields()`
- [x] Remove unexpected read/write side effects when necessary.

Notes:
- [`src/core/database/Schema.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/Schema.php) is now a base/facade
- MySQL-specific schema reading lives in [`src/core/database/dialects/mysql/MysqlSchema.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/mysql/MysqlSchema.php)
- SQLite-specific schema reading now lives in [`src/core/database/dialects/sqlite/SqliteSchema.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/sqlite/SqliteSchema.php)
- dispatch is now based on the PDO driver name
- cache policy is now explicit:
  - default cache mode is file-based
  - memory cache remains available as an explicit fallback option
  - the cache backend is configurable through the schema API
- file persistence is now explicit:
  - normal schema reads no longer create `./schema.json` implicitly
  - file cache loading/saving happens through an explicit cache store
  - persistence happens only through explicit save/refresh flows
- behavior for missing or outdated cache is now explicit:
  - missing file cache is treated as empty cache, not as an error
  - stale cache is not auto-detected or auto-rewritten
  - callers must use explicit refresh/invalidation APIs when they want fresh metadata
- explicit schema cache operations now exist:
  - `useMemoryCache()`
  - `useFileCache()`
  - `useCacheStore()`
  - `clearCache()`
  - `refreshSchema()`
  - `refreshAll()`
  - `saveCache()`
- schema cache backend is now extensible:
  - built-in stores exist for file and memory
  - custom stores can be injected, which leaves room for Redis or similar backends later
  - app bootstrap can now configure schema caching directly before handler initialization
- unsupported PDO drivers now fail explicitly instead of falling back silently

Acceptance criteria:
- the schema layer can be understood and tested without excessive implicit behavior

---

## Phase 3: Stabilize `ActiveRecord`

Goal:
- reduce ambiguity in the main ORM API

Current status:
- completed

Tasks:
- [x] Document the main model lifecycle:
  - [x] `load()`
  - [x] `save()`
  - [x] `remove()`
- [x] Review the distinction between loaded, new and changed attributes.
- [x] Review the use of `__get()` and `__set()` for relations and attributes.
- [x] Define the contract of `tableName()` and primary key.
- [x] Review `save()` behavior for insert vs update.
- [x] Review error handling in `save()` and `remove()`.

Notes:
- `ActiveRecord` constructor now accepts `?Connection`
- the instance keeps a `runtimeConnection` used to preserve multi-connection behavior across hydration and relations
- explicit connections now propagate through:
  - `one()`
  - `all()`
  - `build()`
  - `execute()`
  - `save()`
  - `remove()`
- static helpers also accept optional connection where it matters:
  - `find()`
  - `hasMany()`
  - `hasOne()`
  - `findById()`
  - `findAll()`
  - `put()`
  - `putAll()`
  - `putById()`
  - `create()`
  - `delete()`
  - `deleteAll()`
  - `deleteById()`
- model construction still resolves schema metadata immediately, so object creation is coupled to an available connection and readable schema
- loaded state is now separated from new and changed persisted attributes:
  - `load()` now populates only `attributes`
  - `hydrate()` is public and populates both `attributes` and `oldAttributes`
  - hydrated records loaded by `one()`, `all()`, `hasOne()` and `hasMany()` now pass through `hydrate()` instead of constructor props
  - runtime mutations are now resolved by diffing `attributes` against `oldAttributes`
  - persisted state is synchronized after successful insert/update
- relation caching is now separated from persisted attributes:
  - lazy and eager relations no longer pollute dirty tracking
  - `__get()` no longer treats inherited `ActiveRecord` methods as implicit relations
  - `toArray()` serializes relations without mutating model state
- focused ORM tests now cover:
  - loaded vs new vs changed attribute state
  - lazy relation caching
  - eager relation caching
  - clean serialization after relation loading

Acceptance criteria:
- basic persistence operations have clear, predictable and documented behavior

---

## Phase 4: Stabilize the Embedded Query Builder

Goal:
- guarantee predictable query construction

Current status:
- completed

Tasks:
- [x] Review the contract of:
  - [x] `select()`
  - [x] `where()`
  - [x] `andWhere()`
  - [x] `orWhere()`
  - [x] `whereIn()`
  - [x] `join()` / `innerJoin()` / `leftJoin()` / `rightJoin()`
- [x] Guarantee predictable SQL and params composition.
- [x] Review `rawSql` generation.
- [x] Define the query builder limits for `0.0.2`.

Notes:
- query building state no longer lives inside `ActiveRecord`
- generic builder base lives in [`src/core/database/QueryBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/QueryBuilder.php)
- MySQL query compilation lives in [`src/core/database/dialects/mysql/MysqlQueryBuilder.php`](/home/mathmpr/PhpstormProjects/adige/src/core/database/dialects/mysql/MysqlQueryBuilder.php)
- `rawSql` is now derived from the builder, not stored as a first-class public property on the model
- the public query API now has contract coverage for:
  - fluent return of `self` / `$this`
  - select + join + where + order SQL composition
  - accepted structural formats for `where()` / `andWhere()` / `orWhere()`
  - parameter ordering for scalar and `IN` conditions
- accepted structural formats for `where()` / `andWhere()` / `orWhere()` in `0.0.2` are:
  - equality map with one field/value pair, for example `[':tableName.user_id' => 10]`
  - indexed comparison triplet in the form `[':tableName.id', '>', 1]`
  - `IN` variants remain restricted to the explicit `whereIn()` / `andWhereIn()` / `orWhereIn()` methods with a single field mapped to an array, for example `[':tableName.id' => [1, 2]]`
- mixed `AND` / `OR` grouping now closes parentheses predictably during SQL generation
- query payload resolution now happens in `ActiveRecord` before builder compilation:
  - `INSERT INTO` receives all persisted `attributes`
  - `UPDATE` receives only dirty persisted fields resolved from `attributes` vs `oldAttributes`
  - the builder no longer receives full model state to infer diffs on its own
- the `0.0.2` query builder limit is now explicit:
  - the supported public query surface is restricted to the methods already exposed through `ActiveRecord`
  - no new builder verbs are implied beyond `select()`, `where()`, `andWhere()`, `orWhere()`, `whereIn()`, `andWhereIn()`, `orWhereIn()`, `innerJoin()`, `leftJoin()`, `rightJoin()`, `orderByAsc()` and `orderByDesc()`
  - fluent chaining through these methods must keep returning the same model/query instance

Acceptance criteria:
- query construction is predictable, testable and free from hard-to-track implicit behavior

---

## Phase 5: Review Serialization and Relations

Goal:
- make model and relation materialization predictable

Current status:
- completed

Tasks:
- [x] Review `toArray()` and relation materialization.
- [x] Define policy for serialization fields via `fields()`.
- [x] Review eager loading behavior with `with`.
- [x] Define `Collection` behavior with ORM models.
- [x] Avoid loops or unexpected materialization during serialization.

Notes:
- `hidden` is no longer the serialization contract for `0.0.2`
- `fields()` is now the serialization surface:
  - by default it returns all schema fields plus already-loaded relation names
  - it may be overridden in child models
  - numeric keys mean the value is both the output field name and the resolver name
  - string keys mean the key is the output field name and the value is the resolver
  - string-keyed resolvers may be either a string field/relation name or a callable
  - callables always receive the current model instance
  - callable values on numeric keys are rejected with validation error
- `toArray()` now serializes strictly through `fields()`
- default serialization no longer lazy-loads relations during `toArray()`
- only already-loaded relations are included by the default `fields()` implementation
- default serialization resolves values from persisted attributes and already-loaded relations
- eager loading now uses explicit relation metadata through `RelationDefinition`
- relation methods may return a `RelationDefinition` to support:
  - lazy loading from a single relation definition
  - eager loading in batch through `with()`
- `with()` now performs real eager loading for first-level relations defined via `RelationDefinition`
- current eager loading scope for `0.0.2` is intentionally limited to:
  - `hasOne` and `hasMany`
  - first-level relations only
  - foreign-key matching by relation metadata
- legacy relation methods that still return query objects continue to work, but keep their per-object behavior
- focused ORM tests now cover:
  - default `fields()` serialization of schema fields
  - omission of unloaded relations from default `toArray()`
  - custom `fields()` aliases
  - custom `fields()` callables
  - validation failure for invalid callable placement
  - eager loading of collections without per-item relation queries
- `Collection` remains the stable return type for `all()` and loaded `hasMany` relations
- `Collection::toArray()` is part of the ORM contract:
  - when an item implements `toArray()`, the collection delegates serialization to that item
  - otherwise the raw item is returned unchanged in the resulting array
- `Collection` no longer proxies `__get()` access to the first ORM model item
- `Collection` itself does not trigger lazy loading; it only serializes the models or values already materialized inside it
- cycle protection is now part of serialization behavior:
  - `toArray()` still does not trigger lazy loading for unloaded relations
  - when the same `ActiveRecord` instance reappears in the current serialization tree, the repeated node is serialized as `null`
  - this prevents infinite recursion for already-materialized bidirectional relations

Acceptance criteria:
- models and relations can be serialized with enough predictability for practical use

---

## Phase 6: Create the ORM Test Suite

Goal:
- enable safe refactoring of the ORM layer

Current status:
- completed

Tasks:
- [x] Create minimal fixtures for models and test connections.
- [x] Cover the critical components first:
  - [x] `Connection`
  - [x] `Schema`
  - [x] `ActiveRecord`
- [x] Create contract tests, not only implementation tests.
- [x] Cover at least:
  - [x] default connection
  - [x] connection failure
  - [x] schema reading
  - [x] `save()` insert
  - [x] `save()` update
  - [x] `remove()`
  - [x] `toArray()`

Notes:
- the ORM suite now includes focused contract coverage in:
  - [`tests/Unit/Database/ConnectionContractTest.php`](/home/mathmpr/PhpstormProjects/adige/tests/Unit/Database/ConnectionContractTest.php)
  - [`tests/Unit/Database/SchemaContractTest.php`](/home/mathmpr/PhpstormProjects/adige/tests/Unit/Database/SchemaContractTest.php)
  - [`tests/Unit/Database/ActiveRecordStateTest.php`](/home/mathmpr/PhpstormProjects/adige/tests/Unit/Database/ActiveRecordStateTest.php)
  - [`tests/Unit/Database/QueryBuilderContractTest.php`](/home/mathmpr/PhpstormProjects/adige/tests/Unit/Database/QueryBuilderContractTest.php)
  - [`tests/Unit/Database/CollectionContractTest.php`](/home/mathmpr/PhpstormProjects/adige/tests/Unit/Database/CollectionContractTest.php)
- because the current PHP runtime exposes only `pdo_mysql`, the suite uses mocked PDO/connection doubles for most ORM contract coverage
- connection failure is covered with a real invalid MySQL connection attempt that must raise `CantConnectException`
- schema reading is covered directly through the public `Schema` API, including refresh behavior
- `save()` insert, `save()` update, `remove()`, serialization, eager loading and cycle protection are now covered through `ActiveRecord` contract tests

Acceptance criteria:
- the ORM layer has enough coverage for stabilization and safe refactoring

---

## Immediate Priorities

Best next steps by cost/benefit:
- [x] finalize the `Connection` contract and stop silent MySQL fallback
- [x] decide the `Schema` cache/persistence policy
- [x] document the minimum public ORM API for `0.0.2` based on the current code, not on intended behavior alone
- [x] review `ActiveRecord` construction and schema dependency policy
- [x] start the ORM test suite
