<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use function array_diff_key;
use function array_merge;
use function count;
use Doctrine\DBAL\Platforms\Keywords\Keyword_List;
use Doctrine\DBAL\Platforms\Keywords\Maria_Db_Keywords;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint;
use Doctrine\DBAL\Schema\Table_Diff;
use Doctrine\DBAL\Types\Json_Type;
use Doctrine\Deprecations\Deprecation;
use function in_array;
/**
 * Provides the behavior, features and SQL dialect of the MariaDB database platform of the oldest supported version.
 */
class Maria_Db_Platform extends Abstract_My_Sql_Platform
{
    /**
     * Generate SQL snippets to reverse the aliasing of JSON to LONGTEXT.
     *
     * MariaDb aliases columns specified as JSON to LONGTEXT and sets a CHECK constraint to ensure the column
     * is valid json. This function generates the SQL snippets which reverse this aliasing i.e. report a column
     * as JSON where it was originally specified as such instead of LONGTEXT.
     *
     * The CHECK constraints are stored in information_schema.CHECK_CONSTRAINTS so query that table.
     *
     * @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy.
     */
    public function get_column_type_sql_snippet(string $table_alias, string $database_name): string
    {
        $sub_query_alias = 'i_' . $table_alias;
        $database_name = $this->quote_string_literal($database_name);
        // The check for `CONSTRAINT_SCHEMA = $databaseName` is mandatory here to prevent performance issues
        return <<<SQL
            IF(
                {$table_alias}.DATA_TYPE = 'longtext'
                AND EXISTS(
                    SELECT * FROM information_schema.CHECK_CONSTRAINTS {$sub_query_alias}
                    WHERE {$sub_query_alias}.CONSTRAINT_SCHEMA = {$database_name}
                    AND {$sub_query_alias}.TABLE_NAME = {$table_alias}.TABLE_NAME
                    AND {$sub_query_alias}.CHECK_CLAUSE = CONCAT(
                        'json_valid(`',
                            {$table_alias}.COLUMN_NAME,
                        '`)'
                    )
                ),
                'json',
                {$table_alias}.DATA_TYPE
            )
        SQL;
    }
    /**
     * {@inheritDoc}
     */
    protected function get_pre_alter_table_rename_index_foreign_key_sql(Table_Diff $diff): array
    {
        $sql = [];
        $table_name = $diff->get_old_table()->get_quoted_name($this);
        $modified_foreign_keys = $diff->get_modified_foreign_keys();
        foreach ($this->get_remaining_foreign_key_constraints_requiring_renamed_indexes($diff) as $foreign_key) {
            if (in_array($foreign_key, $modified_foreign_keys, true)) {
                continue;
            }
            $sql[] = $this->get_drop_foreign_key_sql($foreign_key->get_quoted_name($this), $table_name);
        }
        return $sql;
    }
    /**
     * {@inheritDoc}
     */
    protected function get_post_alter_table_index_foreign_key_sql(Table_Diff $diff): array
    {
        return array_merge(parent::get_post_alter_table_index_foreign_key_sql($diff), $this->get_post_alter_table_rename_index_foreign_key_sql($diff));
    }
    /** @return list<string> */
    private function get_post_alter_table_rename_index_foreign_key_sql(Table_Diff $diff): array
    {
        $sql = [];
        $table_name = $diff->get_old_table()->get_quoted_name($this);
        $modified_foreign_keys = $diff->get_modified_foreign_keys();
        foreach ($this->get_remaining_foreign_key_constraints_requiring_renamed_indexes($diff) as $foreign_key) {
            if (in_array($foreign_key, $modified_foreign_keys, true)) {
                continue;
            }
            $sql[] = $this->get_create_foreign_key_sql($foreign_key, $table_name);
        }
        return $sql;
    }
    /**
     * Returns the remaining foreign key constraints that require one of the renamed indexes.
     *
     * "Remaining" here refers to the diff between the foreign keys currently defined in the associated
     * table and the foreign keys to be removed.
     *
     * @param TableDiff $diff The table diff to evaluate.
     *
     * @return ForeignKeyConstraint[]
     */
    private function get_remaining_foreign_key_constraints_requiring_renamed_indexes(Table_Diff $diff): array
    {
        $renamed_indexes = $diff->get_renamed_indexes();
        if (count($renamed_indexes) === 0) {
            return [];
        }
        $foreign_keys = [];
        $remaining_foreign_keys = array_diff_key($diff->get_old_table()->get_foreign_keys(), $diff->get_dropped_foreign_keys());
        foreach ($remaining_foreign_keys as $foreign_key) {
            foreach ($renamed_indexes as $index) {
                if ($foreign_key->intersects_index_columns($index)) {
                    $foreign_keys[] = $foreign_key;
                    break;
                }
            }
        }
        return $foreign_keys;
    }
    /** {@inheritDoc} */
    public function get_column_declaration_sql(string $name, array $column): string
    {
        // MariaDb forces column collation to utf8mb4_bin where the column was declared as JSON so ignore
        // collation and character set for json columns as attempting to set them can cause an error.
        if ($this->get_json_type_declaration_sql([]) === 'JSON' && ($column['type'] ?? null) instanceof Json_Type) {
            unset($column['collation']);
            unset($column['charset']);
        }
        return parent::get_column_declaration_sql($name, $column);
    }
    /** @deprecated */
    protected function create_reserved_keywords_list(): Keyword_List
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6607', '%s is deprecated.', __METHOD__);
        return new Maria_Db_Keywords();
    }
}