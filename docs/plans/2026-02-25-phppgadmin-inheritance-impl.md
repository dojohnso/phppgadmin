# phpPgAdmin Legacy Table Inheritance UI — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `↪` visual indicators and properties page panels for legacy PostgreSQL table inheritance (pre-declarative-partitioning style).

**Architecture:** Three changes in two files: (1) extend the `CASE` expression in `getTables()` for the table list arrow, (2) add two new data functions for filtering inheritance-only relationships, (3) inject two conditional UI blocks into `tblproperties.php` for the panel and back-link.

**Tech Stack:** PHP 8, PostgreSQL 16, phpPgAdmin fork at `/Users/douglasjohnson/DSSDev/phppgadmin16/`. Live container: `rxtracker-web-acute-php8`, phpPgAdmin root at `/srv/dc/phppgadmin/`. Deploy by `podman cp` — no build step needed.

---

### Task 1: Extend `getTables()` display_name CASE for inheritance children

**Files:**
- Modify: `classes/database/Postgres.php:1098-1101`

**Step 1: Make the edit**

Replace the existing CASE expression (lines 1098–1101):

```php
					CASE
						WHEN c.relispartition IS TRUE THEN '↳ ' || c.relname
						ELSE c.relname
					END AS display_name,
```

With:

```php
					CASE
						WHEN c.relispartition IS TRUE
							THEN '↳ ' || c.relname
						WHEN c.relispartition IS NOT TRUE
							AND EXISTS (SELECT 1 FROM pg_catalog.pg_inherits WHERE inhrelid = c.oid)
							THEN '↪ ' || c.relname
						ELSE c.relname
					END AS display_name,
```

**Step 2: Push to container**

```bash
podman cp classes/database/Postgres.php rxtracker-web-acute-php8:/srv/dc/phppgadmin/classes/database/Postgres.php
```

**Step 3: Verify in browser**

Open phpPgAdmin, navigate to a schema that has legacy inherited tables. Confirm:
- Child tables show `↪ tablename` in the list
- Parent tables show no prefix
- Declarative partition children still show `↳ tablename`
- Regular tables unchanged

**Step 4: Commit**

```bash
git add classes/database/Postgres.php
git commit -m "feat: add ↪ arrow prefix for legacy inheritance children in table list"
```

---

### Task 2: Add `getInheritanceChildren()` to `Postgres.php`

**Files:**
- Modify: `classes/database/Postgres.php` — insert after line 1239 (end of `getTableChildren()`)

**Step 1: Insert the new function**

Add after the closing `}` of `getTableChildren()` (after line 1239), before the `getPartitionInfo()` docblock:

```php
	/**
	 * Finds child tables that inherit from $table via legacy inheritance only.
	 * Excludes declarative partition children (relispartition IS NOT TRUE).
	 * @param $table The parent table name
	 * @return A recordset with columns: nspname, relname
	 */
	function getInheritanceChildren($table) {
		$c_schema = $this->_schema;
		$this->clean($c_schema);
		$this->clean($table);

		$sql = "
			SELECT
				cn.nspname,
				cc.relname
			FROM pg_catalog.pg_inherits i
			JOIN pg_catalog.pg_class cc ON cc.oid = i.inhrelid
			JOIN pg_catalog.pg_namespace cn ON cn.oid = cc.relnamespace
			WHERE i.inhparent = (
				SELECT oid FROM pg_catalog.pg_class
				WHERE relname = '{$table}'
				AND relnamespace = (
					SELECT oid FROM pg_catalog.pg_namespace WHERE nspname = '{$c_schema}'
				)
			)
			AND cc.relispartition IS NOT TRUE
			ORDER BY cc.relname";

		return $this->selectSet($sql);
	}
```

**Step 2: Push to container**

```bash
podman cp classes/database/Postgres.php rxtracker-web-acute-php8:/srv/dc/phppgadmin/classes/database/Postgres.php
```

**Step 3: Verify**

No visible change yet — function is added but not wired into the UI. Confirm phpPgAdmin still loads without PHP errors (check browser, no blank pages or warnings).

**Step 4: Commit**

```bash
git add classes/database/Postgres.php
git commit -m "feat: add getInheritanceChildren() for legacy inheritance support"
```

---

### Task 3: Add `getInheritanceParents()` to `Postgres.php`

**Files:**
- Modify: `classes/database/Postgres.php` — insert after `getInheritanceChildren()` (added in Task 2)

**Step 1: Insert the new function**

Add immediately after the closing `}` of `getInheritanceChildren()`:

```php
	/**
	 * Finds parent tables that $table inherits from via legacy inheritance only.
	 * Excludes declarative partition parents (filters to parent relkind = 'r').
	 * Supports multiple-parent inheritance (returns all parents ordered by inhseqno).
	 * @param $table The child table name
	 * @return A recordset with columns: nspname, relname
	 */
	function getInheritanceParents($table) {
		$c_schema = $this->_schema;
		$this->clean($c_schema);
		$this->clean($table);

		$sql = "
			SELECT
				pn.nspname,
				pc.relname
			FROM pg_catalog.pg_inherits i
			JOIN pg_catalog.pg_class cc ON cc.oid = i.inhrelid
			JOIN pg_catalog.pg_namespace cn ON cn.oid = cc.relnamespace
			JOIN pg_catalog.pg_class pc ON pc.oid = i.inhparent
			JOIN pg_catalog.pg_namespace pn ON pn.oid = pc.relnamespace
			WHERE cc.relname = '{$table}'
			AND cn.nspname = '{$c_schema}'
			AND cc.relispartition IS NOT TRUE
			AND pc.relkind = 'r'
			ORDER BY i.inhseqno";

		return $this->selectSet($sql);
	}
```

**Step 2: Push to container**

```bash
podman cp classes/database/Postgres.php rxtracker-web-acute-php8:/srv/dc/phppgadmin/classes/database/Postgres.php
```

**Step 3: Verify**

Still no visible UI change. Confirm phpPgAdmin loads without errors.

**Step 4: Commit**

```bash
git add classes/database/Postgres.php
git commit -m "feat: add getInheritanceParents() for legacy inheritance support"
```

---

### Task 4: Add "Inherited Tables" panel to `tblproperties.php`

**Files:**
- Modify: `tblproperties.php:683` — insert before `// --- End partition support ---`

**Step 1: Make the edit**

Insert the following block immediately before the `// --- End partition support ---` comment (line 683):

```php
	// If this is a legacy inheritance parent, show inherited child tables
	if (isset($tdata->fields['relkind']) && $tdata->fields['relkind'] === 'r') {
		$inheritChildren = $data->getInheritanceChildren($_REQUEST['table']);
		if ($inheritChildren && $inheritChildren->recordCount() > 0) {
			echo "<h3>Inherited Tables</h3>\n";
			echo "<table class=\"data\">\n";
			echo "<tr><th class=\"data\">Table</th></tr>\n";
			$rowclass = 1;
			while (!$inheritChildren->EOF) {
				$cname   = htmlspecialchars($inheritChildren->fields['relname']);
				$cschema = htmlspecialchars($inheritChildren->fields['nspname']);
				echo "<tr class=\"data{$rowclass}\">\n";
				echo "\t<td><a href=\"tblproperties.php?{$misc->href}&amp;schema=".urlencode($cschema)."&amp;table=".urlencode($cname)."\">&#x21AA; {$cname}</a></td>\n";
				echo "</tr>\n";
				$rowclass = ($rowclass == 1) ? 2 : 1;
				$inheritChildren->moveNext();
			}
			echo "</table>\n";
		}
	}
```

Note: `&#x21AA;` is the HTML entity for `↪`.

**Step 2: Push to container**

```bash
podman cp tblproperties.php rxtracker-web-acute-php8:/srv/dc/phppgadmin/tblproperties.php
```

**Step 3: Verify in browser**

Open a legacy inheritance parent table's properties page. Confirm:
- "Inherited Tables" `<h3>` appears below the existing table properties
- Each child table is a clickable link prefixed with `↪`
- Declarative partition parent tables are unaffected (they use `relkind = 'p'`, not `'r'`)
- Regular tables with no children show nothing new

**Step 4: Commit**

```bash
git add tblproperties.php
git commit -m "feat: add Inherited Tables panel on legacy inheritance parent properties page"
```

---

### Task 5: Add "Inherits from" back-link to `tblproperties.php`

**Files:**
- Modify: `tblproperties.php` — insert after Task 4's block, still before `// --- End partition support ---`

**Step 1: Make the edit**

Insert immediately after the closing `}` of the Task 4 block (before `// --- End partition support ---`):

```php
	// If this table has legacy inheritance parents, show back-links
	$isDeclarativeChild = isset($tdata->fields['relispartition'])
		&& ($tdata->fields['relispartition'] === 't' || $tdata->fields['relispartition'] === true);
	if (!$isDeclarativeChild) {
		$inheritParents = $data->getInheritanceParents($_REQUEST['table']);
		if ($inheritParents && $inheritParents->recordCount() > 0) {
			$links = array();
			while (!$inheritParents->EOF) {
				$pname   = htmlspecialchars($inheritParents->fields['relname']);
				$pschema = htmlspecialchars($inheritParents->fields['nspname']);
				$links[] = "<a href=\"tblproperties.php?{$misc->href}&amp;schema=".urlencode($pschema)."&amp;table=".urlencode($pname)."\">{$pname}</a>";
				$inheritParents->moveNext();
			}
			echo "<p class=\"comment\"><strong>Inherits from:</strong> " . implode(', ', $links) . "</p>\n";
		}
	}
```

**Step 2: Push to container**

```bash
podman cp tblproperties.php rxtracker-web-acute-php8:/srv/dc/phppgadmin/tblproperties.php
```

**Step 3: Verify in browser**

Open a legacy inheritance child table's properties page. Confirm:
- "Inherits from: [parent_table]" paragraph appears, with a clickable link to the parent
- If a table inherits from multiple parents, all are shown as comma-separated links
- Declarative partition children are unaffected (`$isDeclarativeChild` guard prevents double-display)
- Regular non-inheriting tables show nothing new

**Step 4: Commit**

```bash
git add tblproperties.php
git commit -m "feat: add Inherits from back-link on legacy inheritance child properties page"
```

---

### Task 6: Update README

**Files:**
- Modify: `README.md`

**Step 1: Update the fork enhancements section**

In the "Fork Enhancements" section of `README.md`, add a bullet under PostgreSQL 16 support (or add a new subsection):

```markdown
- **Legacy inheritance visibility** — child tables created with `INHERITS` show a `↪` prefix in the table list; parent tables show a linked "Inherited Tables" panel; child tables show an "Inherits from" back-link
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: document legacy inheritance UI in README"
```
