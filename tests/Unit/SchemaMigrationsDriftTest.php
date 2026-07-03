<?php

use PHPUnit\Framework\TestCase;

/**
 * schema.sql (used verbatim by fresh installs) must contain everything the
 * migration chain produces — it drifted twice in one week (missing ~26
 * tables once, then missing resource_requests). This test makes that drift
 * a red build: every CREATE TABLE and every ALTER TABLE ... ADD COLUMN in
 * database/migrations/ must be reflected in database/schema.sql.
 */
final class SchemaMigrationsDriftTest extends TestCase
{
    public function testEveryMigrationTableAndColumnExistsInSchema(): void
    {
        $schema = mb_strtolower((string) file_get_contents(BASE_PATH . '/database/schema.sql'));
        $this->assertNotSame('', $schema);

        $missing = [];
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') as $file) {
            $sql = mb_strtolower((string) file_get_contents($file));
            $mig = basename($file);

            // Tables created by migrations must exist in schema.sql.
            preg_match_all('/create\s+table\s+(?:if\s+not\s+exists\s+)?`?(\w+)`?/', $sql, $tables);
            foreach ($tables[1] as $table) {
                if (!preg_match('/create\s+table\s+(?:if\s+not\s+exists\s+)?`?' . $table . '`?\b/', $schema)) {
                    $missing[] = "$mig: table `$table` not in schema.sql";
                }
            }

            // Columns added by migrations must appear inside that table's schema block.
            preg_match_all(
                '/alter\s+table\s+`?(\w+)`?\s+add\s+column\s+(?:if\s+not\s+exists\s+)?`?(\w+)`?/',
                $sql, $cols, PREG_SET_ORDER
            );
            foreach ($cols as $c) {
                [, $table, $column] = $c;
                // Grab the CREATE TABLE block for the table (up to the closing engine clause).
                if (!preg_match('/create\s+table\s+(?:if\s+not\s+exists\s+)?`?' . $table . '`?\s*\((.*?)\)\s*engine/s', $schema, $block)) {
                    $missing[] = "$mig: table `$table` (for column `$column`) not in schema.sql";
                    continue;
                }
                if (!preg_match('/(^|[\s,(])`?' . $column . '`?\s/', $block[1])) {
                    $missing[] = "$mig: column `$table.$column` not in schema.sql";
                }
            }
        }

        $this->assertSame([], $missing,
            "schema.sql has drifted from the migration chain — regenerate it (see DEPLOY.md):\n" . implode("\n", $missing));
    }
}
