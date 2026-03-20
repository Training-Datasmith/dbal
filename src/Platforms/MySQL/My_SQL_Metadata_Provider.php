<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql;

use function array_map;
use function assert;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\Database_Required;
use Doctrine\DBAL\Platforms\Abstract_My_Sql_Platform;
use Doctrine\DBAL\Platforms\Exception\Not_Supported;
use Doctrine\DBAL\Platforms\Maria_Db_Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\Unsupported_Name;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint\Match_Type;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint\Referential_Action;
use Doctrine\DBAL\Schema\Index\Index_Type;
use Doctrine\DBAL\Schema\Metadata\Database_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Foreign_Key_Constraint_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Index_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Metadata_Provider;
use Doctrine\DBAL\Schema\Metadata\Primary_Key_Constraint_Column_Row;
use Doctrine\DBAL\Schema\Metadata\Table_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Table_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\View_Metadata_Row;
use Doctrine\DBAL\Types\Exception\Types_Exception;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function strtr;
final readonly class My_Sql_Metadata_Provider implements Metadata_Provider
{
    /** @see https://mariadb.com/kb/en/library/string-literals/#escape-sequences */
    private const MARIADB_ESCAPE_SEQUENCES = [
        '\0' => "\x00",
        "\\'" => "'",
        '\"' => '"',
        '\b' => "\\b",
        '\n' => "\n",
        '\r' => "\r",
        '\t' => "\t",
        '\Z' => "\x1a",
        '\\\\' => '\\',
        '\%' => '%',
        '\_' => '_',
        // Internally, MariaDB escapes single quotes using the standard syntax
        "''" => "'",
    ];
    /** @var non-empty-string */
    private string $database_name;
    /**
     * @internal This class can be instantiated only by a database platform.
     *
     * @throws Exception
     */
    public function __construct(private Connection $connection, private Abstract_My_Sql_Platform $platform)
    {
        $database_name = $connection->fetch_one('SELECT DATABASE()');
        if ($database_name === null) {
            throw Database_Required::new(__METHOD__);
        }
        $this->database_name = $database_name;
    }
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-schemata-table.html
     */
    public function get_all_database_names(): iterable
    {
        $sql = <<<'SQL'
        SELECT SCHEMA_NAME
        FROM information_schema.SCHEMATA
        ORDER BY SCHEMA_NAME
        SQL;
        foreach ($this->connection->iterate_column($sql) as $database_name) {
            yield new Database_Metadata_Row($database_name);
        }
    }
    /** {@inheritDoc} */
    public function get_all_schema_names(): iterable
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html
     */
    public function get_all_table_names(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
        SQL;
        foreach ($this->connection->iterate_numeric($sql, [$this->database_name]) as $row) {
            yield new Table_Metadata_Row(null, $row[0], []);
        }
    }
    /** {@inheritDoc} */
    public function get_table_columns_for_all_tables(): iterable
    {
        return $this->get_table_columns(null);
    }
    /** {@inheritDoc} */
    public function get_table_columns_for_table(?string $schema_name, string $table_name): iterable
    {
        if ($schema_name !== null) {
            throw Unsupported_Name::from_non_null_schema_name($schema_name, __METHOD__);
        }
        return $this->get_table_columns($table_name);
    }
    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-columns-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_table_columns(?string $table_name): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition to avoid
        // performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['c.TABLE_SCHEMA = ?', 't.TABLE_SCHEMA = ?'];
        $params = [$this->database_name, $this->database_name];
        if ($table_name !== null) {
            $conditions[] = 't.TABLE_NAME = ?';
            $params[] = $table_name;
        }
        $sql = sprintf(<<<'SQL'
        SELECT c.TABLE_NAME,
               c.COLUMN_NAME,
               %s,
               c.COLUMN_TYPE,
               c.CHARACTER_MAXIMUM_LENGTH,
               c.CHARACTER_OCTET_LENGTH,
               c.NUMERIC_PRECISION,
               c.NUMERIC_SCALE,
               c.IS_NULLABLE,
               c.COLUMN_DEFAULT,
               c.EXTRA,
               c.COLUMN_COMMENT,
               c.CHARACTER_SET_NAME,
               c.COLLATION_NAME
        FROM information_schema.COLUMNS c
                 INNER JOIN information_schema.TABLES t
                            ON t.TABLE_NAME = c.TABLE_NAME
        WHERE %s
          AND t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY c.TABLE_NAME,
                 c.ORDINAL_POSITION
        SQL, $this->platform->get_column_type_sql_snippet('c', $this->database_name), implode(' AND ', $conditions));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield $this->create_table_column($row);
        }
    }
    /**
     * @param list<mixed> $row
     *
     * @throws TypesException
     */
    private function create_table_column(array $row): Table_Column_Metadata_Row
    {
        [$table_name, $column_name, $db_type, $column_type, $character_maximum_length, $character_octet_length, $numeric_precision, $numeric_scale, $is_nullable, $column_default, $extra, $column_comment, $character_set_name, $collation_name] = $row;
        $editor = Column::editor()->set_quoted_name($column_name)->set_type_name($this->platform->get_doctrine_type_mapping($db_type));
        if (str_contains((string) $column_type, 'unsigned')) {
            $editor->set_unsigned(true);
        }
        switch ($db_type) {
            case 'char':
            case 'varchar':
                $editor->set_length((int) $character_maximum_length);
                break;
            case 'binary':
            case 'varbinary':
                $editor->set_length((int) $character_octet_length);
                break;
            case 'tinytext':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_TINYTEXT);
                break;
            case 'text':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_TEXT);
                break;
            case 'mediumtext':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_MEDIUMTEXT);
                break;
            case 'tinyblob':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_TINYBLOB);
                break;
            case 'blob':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_BLOB);
                break;
            case 'mediumblob':
                $editor->set_length(Abstract_My_Sql_Platform::LENGTH_LIMIT_MEDIUMBLOB);
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                $editor->set_precision((int) $numeric_precision);
                if ($numeric_scale !== null) {
                    $editor->set_scale((int) $numeric_scale);
                }
                break;
        }
        switch ($db_type) {
            case 'char':
            case 'binary':
                $editor->set_fixed(true);
                break;
            case 'enum':
                $editor->set_values($this->parse_enum_expression($column_type));
                break;
        }
        if ($this->platform instanceof Maria_Db_Platform) {
            $default = $this->parse_maria_db_column_default($this->platform, $column_default);
        } else {
            $default = $column_default;
        }
        $editor->set_default_value($default)->set_not_null($is_nullable !== 'YES')->set_comment($column_comment)->set_charset($character_set_name)->set_collation($collation_name);
        if (str_contains((string) $extra, 'auto_increment')) {
            $editor->set_autoincrement(true);
        }
        return new Table_Column_Metadata_Row(null, $table_name, $editor->create());
    }
    /** @return list<string> */
    private function parse_enum_expression(string $expression): array
    {
        $result = preg_match_all("/'([^']*(?:''[^']*)*)'/", $expression, $matches);
        assert($result !== false);
        return array_map(static fn(string $match): string => strtr($match, ["''" => "'"]), $matches[1]);
    }
    /**
     * Return Doctrine/Mysql-compatible column default values for MariaDB 10.2.7+ servers.
     *
     * - Since MariaDB 10.2.7 column defaults stored in information_schema are quoted to distinguish them from
     *   expressions.
     * - The <code>CURRENT_TIMESTAMP</code>, <code>CURRENT_TIME</code> and <code>CURRENT_DATE</code> expressions
     *   are represented as "current_timestamp()", "curdate()" and "curtime()" respectively.
     * - Quoted 'NULL' is not enforced. It is technically possible to have "null" in some circumstances.
     * - Single quotes are always escaped by doubling, even if the original DDL used backslash escaping.
     *
     * @link https://mariadb.com/kb/en/library/information-schema-columns-table/
     * @link https://jira.mariadb.org/browse/MDEV-10134
     * @link https://jira.mariadb.org/browse/MDEV-13132
     * @link https://jira.mariadb.org/browse/MDEV-14053
     *
     * @param string|null $columnDefault default value as stored in information_schema for MariaDB >= 10.2.7
     */
    private function parse_maria_db_column_default(Maria_Db_Platform $platform, ?string $column_default): ?string
    {
        if ($column_default === 'NULL' || $column_default === null) {
            return null;
        }
        if (preg_match('/^\'(.*)\'$/', $column_default, $matches) === 1) {
            return strtr($matches[1], self::MARIADB_ESCAPE_SEQUENCES);
        }
        return match ($column_default) {
            'current_timestamp()' => $platform->get_current_timestamp_sql(),
            'curdate()' => $platform->get_current_date_sql(),
            'curtime()' => $platform->get_current_time_sql(),
            default => $column_default,
        };
    }
    /** {@inheritDoc} */
    public function get_index_columns_for_all_tables(): iterable
    {
        return $this->get_index_columns(null);
    }
    /** {@inheritDoc} */
    public function get_index_columns_for_table(?string $schema_name, string $table_name): iterable
    {
        if ($schema_name !== null) {
            throw Unsupported_Name::from_non_null_schema_name($schema_name, __METHOD__);
        }
        return $this->get_index_columns($table_name);
    }
    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-statistics-table.html
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_index_columns(?string $table_name): iterable
    {
        $conditions = ['TABLE_SCHEMA = ?'];
        $params = [$this->database_name];
        if ($table_name !== null) {
            $conditions[] = 'TABLE_NAME = ?';
            $params[] = $table_name;
        }
        $sql = sprintf(<<<'SQL'
        SELECT TABLE_NAME,
               INDEX_NAME,
               INDEX_TYPE,
               NON_UNIQUE,
               COLUMN_NAME,
               SUB_PART
        FROM information_schema.STATISTICS
        WHERE %s
          AND INDEX_NAME != 'PRIMARY'
        ORDER BY TABLE_NAME,
            SEQ_IN_INDEX
        SQL, implode(' AND ', $conditions));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            if ($row[5] !== null) {
                $length = (int) $row[5];
                assert($length > 0);
            } else {
                $length = null;
            }
            if ($row[2] === 'FULLTEXT') {
                $type = Index_Type::FULLTEXT;
            } elseif ($row[2] === 'SPATIAL') {
                $type = Index_Type::SPATIAL;
                // the SUB_PART column may contain a non-null value for spatial indexes,
                // but this is not the prefix length
                $length = null;
            } elseif ($row[3]) {
                $type = Index_Type::REGULAR;
            } else {
                $type = Index_Type::UNIQUE;
            }
            yield new Index_Column_Metadata_Row(schemaName: null, tableName: $row[0], indexName: $row[1], type: $type, isClustered: false, predicate: null, columnName: $row[4], columnLength: $length);
        }
    }
    /** {@inheritDoc} */
    public function get_primary_key_constraint_columns_for_all_tables(): iterable
    {
        return $this->get_primary_key_constraint_columns(null);
    }
    /** {@inheritDoc} */
    public function get_primary_key_constraint_columns_for_table(?string $schema_name, string $table_name): iterable
    {
        if ($schema_name !== null) {
            throw Unsupported_Name::from_non_null_schema_name($schema_name, __METHOD__);
        }
        return $this->get_primary_key_constraint_columns($table_name);
    }
    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-constraints-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-key-column-usage-table.html
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function get_primary_key_constraint_columns(?string $table_name): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition to avoid
        // performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['tc.TABLE_SCHEMA = ?', 'kcu.TABLE_SCHEMA = ?'];
        $params = [$this->database_name, $this->database_name];
        if ($table_name !== null) {
            $conditions[] = 'tc.TABLE_NAME = ?';
            $params[] = $table_name;
        }
        $sql = sprintf(<<<'SQL'
        SELECT tc.TABLE_NAME,
               tc.CONSTRAINT_NAME,
               kcu.COLUMN_NAME
        FROM information_schema.TABLE_CONSTRAINTS tc
                 INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                            ON kcu.TABLE_NAME = tc.TABLE_NAME
                                AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE %s
          AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
        ORDER BY TABLE_NAME,
            kcu.ORDINAL_POSITION
        SQL, implode(' AND ', $conditions));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Primary_Key_Constraint_Column_Row(schemaName: null, tableName: $row[0], constraintName: $row[1], isClustered: true, columnName: $row[2]);
        }
    }
    /** {@inheritDoc} */
    public function get_foreign_key_constraint_columns_for_all_tables(): iterable
    {
        return $this->get_foreign_key_constraint_columns(null);
    }
    /** {@inheritDoc} */
    public function get_foreign_key_constraint_columns_for_table(?string $schema_name, string $table_name): iterable
    {
        if ($schema_name !== null) {
            throw Unsupported_Name::from_non_null_schema_name($schema_name, __METHOD__);
        }
        return $this->get_foreign_key_constraint_columns($table_name);
    }
    /**
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-key-column-usage-table.html
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-referential-constraints-table.html
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_foreign_key_constraint_columns(?string $table_name): iterable
    {
        // The schema name is passed multiple times in the WHERE clause instead of using a JOIN condition
        // to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions caused by
        // https://bugs.mysql.com/bug.php?id=81347
        $conditions = ['k.TABLE_SCHEMA = ?', 'c.CONSTRAINT_SCHEMA = ?'];
        $params = [$this->database_name, $this->database_name];
        if ($table_name !== null) {
            $conditions[] = 'k.TABLE_NAME = ?';
            $params[] = $table_name;
        }
        $sql = sprintf(<<<'SQL'
        SELECT k.TABLE_NAME,
               k.CONSTRAINT_NAME,
               k.REFERENCED_TABLE_NAME,
               c.UPDATE_RULE,
               c.DELETE_RULE,
               k.COLUMN_NAME,
               k.REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE k
                 INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS c
                            ON c.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                                AND c.TABLE_NAME = k.TABLE_NAME
        WHERE %s
          AND k.REFERENCED_COLUMN_NAME IS NOT NULL
        ORDER BY k.TABLE_NAME,
                 k.CONSTRAINT_NAME,
                 k.ORDINAL_POSITION
        SQL, implode(' AND ', $conditions));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Foreign_Key_Constraint_Column_Metadata_Row(referencingSchemaName: null, referencingTableName: $row[0], id: null, name: $row[1], referencedSchemaName: null, referencedTableName: $row[2], matchType: Match_Type::SIMPLE, onUpdateAction: $this->create_referential_action($row[3]), onDeleteAction: $this->create_referential_action($row[4]), isDeferrable: false, isDeferred: false, referencingColumnName: $row[5], referencedColumnName: $row[6]);
        }
    }
    private function create_referential_action(string $value): Referential_Action
    {
        $action = Referential_Action::try_from($value);
        assert($action !== null);
        return $action;
    }
    /** {@inheritDoc} */
    public function get_table_options_for_all_tables(): iterable
    {
        return $this->get_table_options(null);
    }
    /** {@inheritDoc} */
    public function get_table_options_for_table(?string $schema_name, string $table_name): iterable
    {
        if ($schema_name !== null) {
            throw Unsupported_Name::from_non_null_schema_name($schema_name, __METHOD__);
        }
        return $this->get_table_options($table_name);
    }
    /**
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function get_table_options(?string $table_name): iterable
    {
        $sql = $this->platform->fetch_table_options_by_table($table_name !== null);
        $params = [$this->database_name];
        if ($table_name !== null) {
            $params[] = $table_name;
        }
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Table_Metadata_Row(null, $row[0], ['engine' => $row[1], 'autoincrement' => $row[2], 'comment' => $row[3], 'create_options' => $this->parse_create_options($row[4]), 'collation' => $row[5], 'charset' => $row[6]]);
        }
    }
    /** @return array<string, string>|array<string, true> */
    private function parse_create_options(?string $string): array
    {
        $options = [];
        if ($string === null || $string === '') {
            return $options;
        }
        foreach (explode(' ', $string) as $pair) {
            $parts = explode('=', $pair, 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
        return $options;
    }
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/information-schema-views-table.html
     */
    public function get_all_views(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABLE_NAME,
               VIEW_DEFINITION
        FROM information_schema.VIEWS
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME
        SQL;
        foreach ($this->connection->iterate_numeric($sql, [$this->database_name]) as $row) {
            yield new View_Metadata_Row(null, ...$row);
        }
    }
    /** {@inheritDoc} */
    public function get_all_sequences(): iterable
    {
        throw Not_Supported::new(__METHOD__);
    }
}