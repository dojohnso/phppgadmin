# phpPgAdmin — Legacy Table Inheritance UI Design

**Date:** 2026-02-25

## Background

Before PostgreSQL introduced declarative partitioning (PG10+), custom partitioning was implemented using table inheritance: child tables are created with `INHERITS (parent_table)` and triggers route inserts to the correct child. The parent and all children are regular tables (`relkind = 'r'`) tracked via `pg_inherits`, with no `relispartition` flag set.

phpPgAdmin currently shows all these tables identically in the list — no visual indication of which are parents and which are children. This feature adds the same relationship visibility we added for declarative partitions.

## Goals

- Child inherited tables get a `↪` prefix in the table list (distinct from `↳` used for declarative partition children)
- Parent tables show an "Inherited Tables" panel on their properties page with linked child names
- Child tables show an "Inherits from: [parent]" back-link on their properties page
- Multiple-parent inheritance is supported (PG allows it; back-links are comma-separated)
- No visual change for tables that are neither parents nor inheritance children

## Non-Goals

- No changes to the existing declarative partition UI
- No changes to `getTableParents()` / `getTableChildren()` (existing functions stay intact)

## Design

### 1. Table List (`getTables()` in `Postgres.php`)

Extend the `CASE` expression in the `$all=false` branch:

```sql
CASE
    WHEN c.relispartition IS TRUE
        THEN '↳ ' || c.relname
    WHEN c.relispartition IS NOT TRUE
        AND EXISTS (SELECT 1 FROM pg_catalog.pg_inherits WHERE inhrelid = c.oid)
        THEN '↪ ' || c.relname
    ELSE c.relname
END AS display_name
```

Declarative partition children (`relispartition IS TRUE`) are caught by the first branch and never reach the second. Legacy inheritance children fall through to the `EXISTS` check. Parent tables remain unlabeled.

### 2. New Data Functions (`Postgres.php`)

Two new functions added after the existing `getTableChildren()`:

**`getInheritanceChildren($table)`**
Returns child tables that inherit from `$table` via legacy inheritance only (`relispartition IS NOT TRUE`). Returns columns: `nspname`, `relname`.

**`getInheritanceParents($table)`**
Returns parent tables that `$table` inherits from via legacy inheritance only (parent `relkind = 'r'`, not `'p'`). Returns columns: `nspname`, `relname`.

These are distinct from the existing `getTableChildren()` / `getTableParents()` which return all `pg_inherits` relationships including declarative partitions.

### 3. Properties Page (`tblproperties.php`)

Two new conditional blocks added after the existing partition blocks:

**Inheritance parent block** — triggered when `relkind = 'r'` and `getInheritanceChildren()` returns rows:
- `<h3>Inherited Tables</h3>`
- HTML table with linked child table names (links to each child's tblproperties.php page)

**Inheritance child block** — triggered when `relispartition IS NOT TRUE` and `getInheritanceParents()` returns rows:
- `<p>Inherits from: <a>parent1</a>, <a>parent2</a></p>`
- Iterates all parent rows; comma-separated links for multiple-parent case

The `relispartition IS NOT TRUE` guard ensures declarative partition children don't show the inheritance back-link.

## Symbol Key (combined)

| Symbol | Meaning |
|--------|---------|
| `↳`    | Declarative partition child (native PG partitioning) |
| `↪`    | Legacy inheritance child (trigger-based / manual partitioning) |
| *(none)* | Parent table (partition or inheritance) |
