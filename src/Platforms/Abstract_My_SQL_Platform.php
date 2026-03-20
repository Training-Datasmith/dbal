<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use function array_diff;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\Invalid_Column_Type\Column_Values_Required;
use Doctrine\DBAL\Platforms\Keywords\Keyword_List;
use Doctrine\DBAL\Platforms\Keywords\My_Sql_Keywords;
use Doctrine\DBAL\Platforms\My_Sql\My_Sql_Metadata_Provider;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\My_Sql_Schema_Manager;
use Doctrine\DBAL\Schema\Name\Unquoted_Identifier_Folding;
use Doctrine\DBAL\Schema\Table_Diff;
use Doctrine\DBAL\SQL\Builder\Default_Select_Sql_Builder;
use Doctrine\DBAL\SQL\Builder\Select_Sql_Builder;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Transaction_Isolation_Level;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function sprintf;
use function str_replace;
use function strtolower;
/**
 * Provides the base implementation for the lowest versions of supported MySQL-like database platforms.
 */
abstract class Abstract_My_Sql_Platform extends Abstract_Platform
{
    final public const LENGTH_LIMIT_TINYTEXT = 255;
    final public const LENGTH_LIMIT_TEXT = 65535;
    final public const LENGTH_LIMIT_MEDIUMTEXT = 16777215;
    final public const LENGTH_LIMIT_TINYBLOB = 255;
    final public const LENGTH_LIMIT_BLOB = 65535;
    final public const LENGTH_LIMIT_MEDIUMBLOB = 16777215;
    public function __construct()
    {
        parent::__construct(Unquoted_Identifier_Folding::NONE);
    }
    protected function do_modify_limit_query(string $query, ?int $limit, int $offset): string
    {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);
            if ($offset > 0) {
                $query .= sprintf(' OFFSET %d', $offset);
            }
        } elseif ($offset > 0) {
            // 2^64-1 is the maximum of unsigned BIGINT, the biggest limit possible
            $query .= sprintf(' LIMIT 18446744073709551615 OFFSET %d', $offset);
        }
        return $query;
    }
    public function quote_single_identifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }
    public function get_regexp_expression(): string
    {
        return 'RLIKE';
    }
    public function get_locate_expression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('LOCATE(%s, %s)', $substring, $string);
        }
        return sprintf('LOCATE(%s, %s, %s)', $substring, $string, $start);
    }
    public function get_concat_expression(string ...$string): string
    {
        return sprintf('CONCAT(%s)', implode(', ', $string));
    }
    protected function get_date_arithmetic_interval_expression(string $date, string $operator, string $interval, Date_Interval_Unit $unit): string
    {
        $function = $operator === '+' ? 'DATE_ADD' : 'DATE_SUB';
        return $function . '(' . $date . ', INTERVAL ' . $interval . ' ' . $unit->value . ')';
    }
    public function get_date_diff_expression(string $date1, string $date2): string
    {
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }
    public function get_current_database_expression(): string
    {
        return 'DATABASE()';
    }
    public function get_length_expression(string $string): string
    {
        return 'CHAR_LENGTH(' . $string . ')';
    }
    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function get_list_databases_sql(): string
    {
        return 'SHOW DATABASES';
    }
    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function get_list_views_sql(string $database): string
    {
        return 'SELECT * FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ' . $this->quote_string_literal($database);
    }
    /**
     * {@inheritDoc}
     */
    public function get_json_type_declaration_sql(array $column): string
    {
        if (!empty($column['jsonb'])) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6939', 'The "jsonb" column platform option is deprecated. Use the "JSONB" type instead.');
        }
        return 'JSON';
    }
    /**
     * Gets the SQL snippet used to declare a CLOB column type.
     *     TINYTEXT   : 2 ^  8 - 1 = 255
     *     TEXT       : 2 ^ 16 - 1 = 65535
     *     MEDIUMTEXT : 2 ^ 24 - 1 = 16777215
     *     LONGTEXT   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function get_clob_type_declaration_sql(array $column): string
    {
        if (!empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];
            if ($length <= static::LENGTH_LIMIT_TINYTEXT) {
                return 'TINYTEXT';
            }
            if ($length <= static::LENGTH_LIMIT_TEXT) {
                return 'TEXT';
            }
            if ($length <= static::LENGTH_LIMIT_MEDIUMTEXT) {
                return 'MEDIUMTEXT';
            }
        }
        return 'LONGTEXT';
    }
    /**
     * {@inheritDoc}
     */
    public function get_date_time_type_declaration_sql(array $column): string
    {
        if (isset($column['version']) && $column['version'] === true) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6940', 'The "version" column platform option is deprecated.');
            return 'TIMESTAMP';
        }
        return 'DATETIME';
    }
    /**
     * {@inheritDoc}
     */
    public function get_date_type_declaration_sql(array $column): string
    {
        return 'DATE';
    }
    /**
     * {@inheritDoc}
     */
    public function get_time_type_declaration_sql(array $column): string
    {
        return 'TIME';
    }
    /**
     * {@inheritDoc}
     */
    public function get_boolean_type_declaration_sql(array $column): string
    {
        return 'TINYINT';
    }
    /**
     * {@inheritDoc}
     *
     * MySQL supports this through AUTO_INCREMENT columns.
     */
    public function supports_identity_columns(): bool
    {
        return true;
    }
    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supports_inline_column_comments(): bool
    {
        return true;
    }
    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supports_column_collation(): bool
    {
        return true;
    }
    /**
     * The SQL snippet required to elucidate a column type
     *
     * Returns a column type SELECT snippet string
     *
     * @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy.
     */
    public function get_column_type_sql_snippet(string $table_alias, string $database_name): string
    {
        return $table_alias . '.DATA_TYPE';
    }
    /**
     * {@inheritDoc}
     */
    protected function _get_create_table_sql(string $name, array $columns, array $options = []): array
    {
        $this->validate_create_table_options($options, __METHOD__);
        $query_fields = $this->get_column_declaration_list_sql($columns);
        foreach ($options['uniqueConstraints'] as $definition) {
            $query_fields .= ', ' . $this->get_unique_constraint_declaration_sql($definition);
        }
        // add all indexes
        foreach ($options['indexes'] as $definition) {
            $query_fields .= ', ' . $this->get_index_declaration_sql($definition);
        }
        // attach all primary keys
        if (!empty($options['primary'])) {
            $key_columns = array_unique(array_values($options['primary']));
            $query_fields .= ', PRIMARY KEY (' . implode(', ', $key_columns) . ')';
        }
        $sql = ['CREATE'];
        if (!empty($options['temporary'])) {
            $sql[] = 'TEMPORARY';
        }
        $sql[] = 'TABLE ' . $name . ' (' . $query_fields . ')';
        $table_options = $this->build_table_options($options);
        if ($table_options !== '') {
            $sql[] = $table_options;
        }
        if (isset($options['partition_options'])) {
            $sql[] = $options['partition_options'];
        }
        $sql = [implode(' ', $sql)];
        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $definition) {
                $sql[] = $this->get_create_foreign_key_sql($definition, $name);
            }
        }
        return $sql;
    }
    public function create_select_sql_builder(): Select_Sql_Builder
    {
        return new Default_Select_Sql_Builder($this, 'FOR UPDATE', null);
    }
    /**
     * Build SQL for table options
     *
     * @param mixed[] $options
     */
    private function build_table_options(array $options): string
    {
        if (isset($options['table_options'])) {
            return $options['table_options'];
        }
        $table_options = [];
        if (isset($options['charset'])) {
            $table_options[] = sprintf('DEFAULT CHARACTER SET %s', $options['charset']);
        }
        if (isset($options['collation'])) {
            $table_options[] = $this->get_column_collation_declaration_sql($options['collation']);
        }
        if (isset($options['engine'])) {
            $table_options[] = sprintf('ENGINE = %s', $options['engine']);
        }
        // Auto increment
        if (isset($options['auto_increment'])) {
            $table_options[] = sprintf('AUTO_INCREMENT = %s', $options['auto_increment']);
        }
        // Comment
        if (isset($options['comment'])) {
            $table_options[] = sprintf('COMMENT = %s ', $this->quote_string_literal($options['comment']));
        }
        // Row format
        if (isset($options['row_format'])) {
            $table_options[] = sprintf('ROW_FORMAT = %s', $options['row_format']);
        }
        return implode(' ', $table_options);
    }
    /**
     * {@inheritDoc}
     */
    public function get_alter_table_sql(Table_Diff $diff): array
    {
        $query_parts = [];
        foreach ($diff->get_added_columns() as $column) {
            $column_properties = array_merge($column->to_array(), ['comment' => $column->get_comment()]);
            $query_parts[] = 'ADD ' . $this->get_column_declaration_sql($column->get_quoted_name($this), $column_properties);
        }
        foreach ($diff->get_dropped_columns() as $column) {
            $query_parts[] = 'DROP ' . $column->get_quoted_name($this);
        }
        foreach ($diff->get_changed_columns() as $column_diff) {
            $new_column = $column_diff->get_new_column();
            $new_column_properties = array_merge($new_column->to_array(), ['comment' => $new_column->get_comment()]);
            $old_column = $column_diff->get_old_column();
            $query_parts[] = 'CHANGE ' . $old_column->get_quoted_name($this) . ' ' . $this->get_column_declaration_sql($new_column->get_quoted_name($this), $new_column_properties);
        }
        $dropped_indexes = $this->index_indexes_by_lower_case_name($diff->get_dropped_indexes());
        $added_indexes = $this->index_indexes_by_lower_case_name($diff->get_added_indexes());
        $no_longer_primary_key_columns = [];
        if (isset($dropped_indexes['primary'])) {
            $query_parts[] = 'DROP PRIMARY KEY';
            $no_longer_primary_key_columns = $dropped_indexes['primary']->get_columns();
        }
        if (isset($added_indexes['primary'])) {
            $key_columns = array_values(array_unique($added_indexes['primary']->get_columns()));
            $query_parts[] = 'ADD PRIMARY KEY (' . implode(', ', $key_columns) . ')';
            $no_longer_primary_key_columns = array_diff($no_longer_primary_key_columns, $added_indexes['primary']->get_columns());
            $diff->unset_added_index($added_indexes['primary']);
        }
        $table_sql = [];
        if (isset($dropped_indexes['primary'])) {
            $old_table = $diff->get_old_table();
            foreach ($no_longer_primary_key_columns as $column_name) {
                if (!$old_table->has_column($column_name)) {
                    continue;
                }
                $column = $old_table->get_column($column_name);
                if ($column->get_autoincrement()) {
                    $table_sql = array_merge($table_sql, $this->get_pre_alter_table_alter_primary_key_sql($diff, $dropped_indexes['primary']));
                    break;
                }
            }
            $diff->unset_dropped_index($dropped_indexes['primary']);
        }
        if (count($query_parts) > 0) {
            $table_sql[] = 'ALTER TABLE ' . $diff->get_old_table()->get_quoted_name($this) . ' ' . implode(', ', $query_parts);
        }
        return array_merge($this->get_pre_alter_table_index_foreign_key_sql($diff), $table_sql, $this->get_post_alter_table_index_foreign_key_sql($diff));
    }
    /**
     * {@inheritDoc}
     */
    protected function get_pre_alter_table_index_foreign_key_sql(Table_Diff $diff): array
    {
        $sql = [];
        $table_name_sql = $diff->get_old_table()->get_quoted_name($this);
        foreach ($diff->get_modified_indexes() as $changed_index) {
            $sql = array_merge($sql, $this->get_pre_alter_table_alter_primary_key_sql($diff, $changed_index));
        }
        foreach ($diff->get_dropped_indexes() as $dropped_index) {
            $sql = array_merge($sql, $this->get_pre_alter_table_alter_primary_key_sql($diff, $dropped_index));
            foreach ($diff->get_added_indexes() as $added_index) {
                if ($dropped_index->get_columns() !== $added_index->get_columns()) {
                    continue;
                }
                $index_clause = 'INDEX ' . $added_index->get_name();
                if ($added_index->is_primary()) {
                    $index_clause = 'PRIMARY KEY';
                } elseif ($added_index->is_unique()) {
                    $index_clause = 'UNIQUE INDEX ' . $added_index->get_name();
                }
                $query = 'ALTER TABLE ' . $table_name_sql . ' DROP INDEX ' . $dropped_index->get_name() . ', ';
                $query .= 'ADD ' . $index_clause;
                $query .= ' (' . implode(', ', $added_index->get_quoted_columns($this)) . ')';
                $sql[] = $query;
                $diff->unset_added_index($added_index);
                $diff->unset_dropped_index($dropped_index);
                break;
            }
        }
        return array_merge($sql, $this->get_pre_alter_table_alter_index_foreign_key_sql($diff), parent::get_pre_alter_table_index_foreign_key_sql($diff), $this->get_pre_alter_table_rename_index_foreign_key_sql($diff));
    }
    /** @return list<string> */
    private function get_pre_alter_table_alter_primary_key_sql(Table_Diff $diff, Index $index): array
    {
        if (!$index->is_primary()) {
            return [];
        }
        $table = $diff->get_old_table();
        $sql = [];
        $table_name_sql = $table->get_quoted_name($this);
        // Dropping primary keys requires to unset autoincrement attribute on the particular column first.
        foreach ($index->get_columns() as $column_name) {
            if (!$table->has_column($column_name)) {
                continue;
            }
            $column = $table->get_column($column_name);
            if (!$column->get_autoincrement()) {
                continue;
            }
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6841', 'Relying on the auto-increment attribute of a column being automatically dropped once a column' . ' is no longer part of the primary key constraint is deprecated. Instead, drop the auto-increment' . ' attribute explicitly.');
            $column->set_autoincrement(false);
            $sql[] = 'ALTER TABLE ' . $table_name_sql . ' MODIFY ' . $this->get_column_declaration_sql($column->get_quoted_name($this), $column->to_array());
            // original autoincrement information might be needed later on by other parts of the table alteration
            $column->set_autoincrement(true);
        }
        return $sql;
    }
    /**
     * @param TableDiff $diff The table diff to gather the SQL for.
     *
     * @return list<string>
     */
    private function get_pre_alter_table_alter_index_foreign_key_sql(Table_Diff $diff): array
    {
        $table = $diff->get_old_table();
        $primary_key = $table->get_primary_key();
        if ($primary_key === null) {
            return [];
        }
        $primary_key_columns = [];
        foreach ($primary_key->get_columns() as $column_name) {
            if (!$table->has_column($column_name)) {
                continue;
            }
            $primary_key_columns[] = $table->get_column($column_name);
        }
        if (count($primary_key_columns) === 0) {
            return [];
        }
        $sql = [];
        $table_name_sql = $table->get_quoted_name($this);
        foreach ($diff->get_modified_indexes() as $changed_index) {
            // Changed primary key
            if (!$changed_index->is_primary()) {
                continue;
            }
            foreach ($primary_key_columns as $column) {
                // Check if an autoincrement column was dropped from the primary key.
                if (!$column->get_autoincrement()) {
                    continue;
                }
                if (in_array($column->get_name(), $changed_index->get_columns(), true)) {
                    continue;
                }
                // The autoincrement attribute needs to be removed from the dropped column
                // before we can drop and recreate the primary key.
                $column->set_autoincrement(false);
                $sql[] = 'ALTER TABLE ' . $table_name_sql . ' MODIFY ' . $this->get_column_declaration_sql($column->get_quoted_name($this), $column->to_array());
                // Restore the autoincrement attribute as it might be needed later on
                // by other parts of the table alteration.
                $column->set_autoincrement(true);
            }
        }
        return $sql;
    }
    /**
     * @param TableDiff $diff The table diff to gather the SQL for.
     *
     * @return list<string>
     */
    protected function get_pre_alter_table_rename_index_foreign_key_sql(Table_Diff $diff): array
    {
        return [];
    }
    protected function get_create_index_sql_flags(Index $index): string
    {
        $type = '';
        if ($index->is_unique()) {
            $type .= 'UNIQUE ';
        } elseif ($index->has_flag('fulltext')) {
            $type .= 'FULLTEXT ';
        } elseif ($index->has_flag('spatial')) {
            $type .= 'SPATIAL ';
        }
        return $type;
    }
    /**
     * {@inheritDoc}
     */
    public function get_integer_type_declaration_sql(array $column): string
    {
        return 'INT' . $this->_get_common_integer_type_declaration_sql($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_big_int_type_declaration_sql(array $column): string
    {
        return 'BIGINT' . $this->_get_common_integer_type_declaration_sql($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_small_int_type_declaration_sql(array $column): string
    {
        return 'SMALLINT' . $this->_get_common_integer_type_declaration_sql($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_float_declaration_sql(array $column): string
    {
        return 'DOUBLE PRECISION' . $this->get_unsigned_declaration($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_small_float_declaration_sql(array $column): string
    {
        return 'FLOAT' . $this->get_unsigned_declaration($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_decimal_type_declaration_sql(array $column): string
    {
        return parent::get_decimal_type_declaration_sql($column) . $this->get_unsigned_declaration($column);
    }
    /**
     * {@inheritDoc}
     */
    public function get_enum_declaration_sql(array $column): string
    {
        if (!isset($column['values']) || !is_array($column['values']) || $column['values'] === []) {
            throw Column_Values_Required::new($this, 'ENUM');
        }
        return sprintf('ENUM(%s)', implode(', ', array_map($this->quote_string_literal(...), $column['values'])));
    }
    /**
     * Get unsigned declaration for a column.
     *
     * @param mixed[] $columnDef
     */
    private function get_unsigned_declaration(array $column_def): string
    {
        return !empty($column_def['unsigned']) ? ' UNSIGNED' : '';
    }
    /**
     * {@inheritDoc}
     */
    protected function _get_common_integer_type_declaration_sql(array $column): string
    {
        $sql = $this->get_unsigned_declaration($column);
        if (!empty($column['autoincrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }
        return $sql;
    }
    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function get_column_charset_declaration_sql(string $charset): string
    {
        return 'CHARACTER SET ' . $charset;
    }
    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function get_advanced_foreign_key_options_sql(Foreign_Key_Constraint $foreign_key): string
    {
        $query = '';
        if ($foreign_key->has_option('match')) {
            $query .= ' MATCH ' . $foreign_key->get_option('match');
        }
        return $query . parent::get_advanced_foreign_key_options_sql($foreign_key);
    }
    public function get_drop_index_sql(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name . ' ON ' . $table;
    }
    /**
     * The `ALTER TABLE ... DROP CONSTRAINT` syntax is only available as of MySQL 8.0.19.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/alter-table.html
     */
    public function get_drop_unique_constraint_sql(string $name, string $table_name): string
    {
        return $this->get_drop_index_sql($name, $table_name);
    }
    public function get_set_transaction_isolation_sql(Transaction_Isolation_Level $level): string
    {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $this->_get_transaction_isolation_level_sql($level);
    }
    protected function initialize_doctrine_type_mappings(): void
    {
        $this->doctrine_type_mapping = ['bigint' => Types::BIGINT, 'binary' => Types::BINARY, 'blob' => Types::BLOB, 'char' => Types::STRING, 'date' => Types::DATE_MUTABLE, 'datetime' => Types::DATETIME_MUTABLE, 'decimal' => Types::DECIMAL, 'double' => Types::FLOAT, 'enum' => Types::ENUM, 'float' => Types::SMALLFLOAT, 'int' => Types::INTEGER, 'integer' => Types::INTEGER, 'json' => Types::JSON, 'longblob' => Types::BLOB, 'longtext' => Types::TEXT, 'mediumblob' => Types::BLOB, 'mediumint' => Types::INTEGER, 'mediumtext' => Types::TEXT, 'numeric' => Types::DECIMAL, 'real' => Types::FLOAT, 'set' => Types::SIMPLE_ARRAY, 'smallint' => Types::SMALLINT, 'string' => Types::STRING, 'text' => Types::TEXT, 'time' => Types::TIME_MUTABLE, 'timestamp' => Types::DATETIME_MUTABLE, 'tinyblob' => Types::BLOB, 'tinyint' => Types::BOOLEAN, 'tinytext' => Types::TEXT, 'varbinary' => Types::BINARY, 'varchar' => Types::STRING, 'year' => Types::DATE_MUTABLE];
    }
    /** @deprecated */
    protected function create_reserved_keywords_list(): Keyword_List
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6607', '%s is deprecated.', __METHOD__);
        return new My_Sql_Keywords();
    }
    /**
     * {@inheritDoc}
     *
     * MySQL commits a transaction implicitly when DROP TABLE is executed, however not
     * if DROP TEMPORARY TABLE is executed.
     */
    public function get_drop_temporary_table_sql(string $table): string
    {
        return 'DROP TEMPORARY TABLE ' . $table;
    }
    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     *     TINYBLOB   : 2 ^  8 - 1 = 255
     *     BLOB       : 2 ^ 16 - 1 = 65535
     *     MEDIUMBLOB : 2 ^ 24 - 1 = 16777215
     *     LONGBLOB   : 2 ^ 32 - 1 = 4294967295
     *
     * {@inheritDoc}
     */
    public function get_blob_type_declaration_sql(array $column): string
    {
        if (!empty($column['length']) && is_numeric($column['length'])) {
            $length = $column['length'];
            if ($length <= static::LENGTH_LIMIT_TINYBLOB) {
                return 'TINYBLOB';
            }
            if ($length <= static::LENGTH_LIMIT_BLOB) {
                return 'BLOB';
            }
            if ($length <= static::LENGTH_LIMIT_MEDIUMBLOB) {
                return 'MEDIUMBLOB';
            }
        }
        return 'LONGBLOB';
    }
    public function quote_string_literal(string $str): string
    {
        // MySQL requires backslashes to be escaped as well.
        $str = str_replace('\\', '\\\\', $str);
        return parent::quote_string_literal($str);
    }
    public function get_default_transaction_isolation_level(): Transaction_Isolation_Level
    {
        return Transaction_Isolation_Level::REPEATABLE_READ;
    }
    /** @deprecated */
    public function supports_column_length_indexes(): bool
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6886', '%s is deprecated.', __METHOD__);
        return true;
    }
    public function create_metadata_provider(Connection $connection): My_Sql_Metadata_Provider
    {
        return new My_Sql_Metadata_Provider($connection, $this);
    }
    public function create_schema_manager(Connection $connection): My_Sql_Schema_Manager
    {
        return new My_Sql_Schema_Manager($connection, $this);
    }
    /**
     * @param array<Index> $indexes
     *
     * @return array<string,Index>
     */
    private function index_indexes_by_lower_case_name(array $indexes): array
    {
        $result = [];
        foreach ($indexes as $index) {
            $result[strtolower($index->get_name())] = $index;
        }
        return $result;
    }
    /** @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy. */
    public function fetch_table_options_by_table(bool $include_table_name): string
    {
        $sql = <<<'SQL'
            SELECT t.TABLE_NAME,
                   t.ENGINE,
                   t.AUTO_INCREMENT,
                   t.TABLE_COMMENT,
                   t.CREATE_OPTIONS,
                   t.TABLE_COLLATION,
                   ccsa.CHARACTER_SET_NAME
              FROM information_schema.TABLES t
                INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
                  ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
        SQL;
        $conditions = ['t.TABLE_SCHEMA = ?'];
        if ($include_table_name) {
            $conditions[] = 't.TABLE_NAME = ?';
        }
        $conditions[] = "t.TABLE_TYPE = 'BASE TABLE'";
        return $sql . ' WHERE ' . implode(' AND ', $conditions);
    }
    public function create_sql_parser(): Parser
    {
        return new Parser(true);
    }
}