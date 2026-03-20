<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Db2;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\Not_Supported;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\Unsupported_Name;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint\Match_Type;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint\Referential_Action;
use Doctrine\DBAL\Schema\Index\Index_Type;
use Doctrine\DBAL\Schema\Metadata\Foreign_Key_Constraint_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Index_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Metadata_Provider;
use Doctrine\DBAL\Schema\Metadata\Primary_Key_Constraint_Column_Row;
use Doctrine\DBAL\Schema\Metadata\Table_Column_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\Table_Metadata_Row;
use Doctrine\DBAL\Schema\Metadata\View_Metadata_Row;
use Doctrine\DBAL\Types\Exception\Types_Exception;
use Doctrine\DBAL\Types\Types;
use function implode;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;
/** @link https://www.ibm.com/docs/en/db2/12.1.0?topic=sql-catalog-views */
final readonly class Db2metadata_Provider implements Metadata_Provider
{
    /** @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatreferences */
    private const REFERENTIAL_ACTIONS = ['A' => Referential_Action::NO_ACTION, 'C' => Referential_Action::CASCADE, 'N' => Referential_Action::SET_NULL, 'R' => Referential_Action::RESTRICT];
    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private DB2Platform $platform)
    {
    }
    /** {@inheritDoc} */
    public function get_all_database_names(): iterable
    {
        throw Not_Supported::new(__METHOD__);
    }
    /** {@inheritDoc} */
    public function get_all_schema_names(): iterable
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * {@inheritDoc}
     *
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     */
    public function get_all_table_names(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABNAME
        FROM SYSCAT.TABLES
        WHERE TABSCHEMA = CURRENT USER
          AND TYPE = 'T'
        ORDER BY TABNAME
        SQL;
        foreach ($this->connection->iterate_numeric($sql) as $row) {
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatcolumns
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_table_columns(?string $table_name): iterable
    {
        $params = [];
        $sql = sprintf(<<<'SQL'
        SELECT C.TABNAME,
               C.COLNAME,
               C.TYPENAME,
               C.CODEPAGE,
               C.NULLS,
               C.LENGTH,
               C.SCALE,
               C.REMARKS,
               C.GENERATED,
               C.DEFAULT
        FROM SYSCAT.COLUMNS C
                 JOIN SYSCAT.TABLES AS T
                      ON T.TABSCHEMA = C.TABSCHEMA
                          AND T.TABNAME = C.TABNAME
        WHERE %s
          AND T.TYPE = 'T'
        ORDER BY C.TABNAME,
                 C.COLNO
        SQL, $this->build_table_query_predicate('C', $table_name, $params));
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
        [$table_name, $column_name, $type_name, $code_page, $nulls, $length, $scale, $remarks, $generated, $default] = $row;
        $editor = Column::editor()->set_quoted_name($column_name);
        $type = $this->platform->get_doctrine_type_mapping($type_name);
        switch (strtolower((string) $type_name)) {
            case 'varchar':
                if ($code_page === 0) {
                    $type = Types::BINARY;
                }
                $editor->set_length($length);
                break;
            case 'character':
                if ($code_page === 0) {
                    $type = Types::BINARY;
                }
                $editor->set_length($length)->set_fixed(true);
                break;
            case 'clob':
                $editor->set_length($length);
                break;
            case 'decimal':
            case 'double':
            case 'real':
                $editor->set_precision($length)->set_scale($scale);
                break;
        }
        $editor->set_type_name($type)->set_not_null($nulls === 'N')->set_default_value($this->parse_default_expression($default))->set_autoincrement($generated === 'D');
        if ($remarks !== null) {
            $editor->set_comment($remarks);
        }
        return new Table_Column_Metadata_Row(null, $table_name, $editor->create());
    }
    private function parse_default_expression(?string $expression): ?string
    {
        if ($expression === null || $expression === 'NULL') {
            return null;
        }
        if (preg_match('/^\'(.*)\'$/s', $expression, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }
        return $expression;
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatindexcoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatindexes
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_index_columns(?string $table_name): iterable
    {
        $params = [];
        $sql = sprintf(<<<'SQL'
        SELECT I.TABNAME,
               I.INDNAME,
               I.UNIQUERULE,
               ICU.COLNAME
        FROM SYSCAT.INDEXES AS I
                 JOIN SYSCAT.TABLES AS T
                      ON I.TABSCHEMA = T.TABSCHEMA
                             AND I.TABNAME = T.TABNAME
                 JOIN SYSCAT.INDEXCOLUSE AS ICU
                      ON I.INDSCHEMA = ICU.INDSCHEMA
                             AND I.INDNAME = ICU.INDNAME
        WHERE %s
          AND T.TYPE = 'T'
          AND I.UNIQUERULE != 'P'
        ORDER BY I.TABNAME,
            I.INDNAME,
            ICU.COLSEQ
        SQL, $this->build_table_query_predicate('I', $table_name, $params));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Index_Column_Metadata_Row(schemaName: null, tableName: $row[0], indexName: $row[1], type: $row[2] === 'U' ? Index_Type::UNIQUE : Index_Type::REGULAR, isClustered: false, predicate: null, columnName: $row[3], columnLength: null);
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatcoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattabconst
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function get_primary_key_constraint_columns(?string $table_name): iterable
    {
        $params = [];
        $sql = sprintf(<<<'SQL'
        SELECT TC.TABNAME,
               TC.CONSTNAME,
               KCU.COLNAME
        FROM SYSCAT.TABCONST TC
                 JOIN SYSCAT.KEYCOLUSE KCU
                      ON KCU.TABSCHEMA = TC.TABSCHEMA
                          AND KCU.TABNAME = TC.TABNAME
                          AND KCU.CONSTNAME = TC.CONSTNAME
        WHERE %s
          AND TC.TYPE = 'P'
        ORDER BY TC.TABNAME,
            TC.CONSTNAME,
            KCU.COLSEQ
        SQL, $this->build_table_query_predicate('TC', $table_name, $params));
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatkeycoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatreferences
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function get_foreign_key_constraint_columns(?string $table_name): iterable
    {
        $params = [];
        $sql = sprintf(<<<'SQL'
        SELECT R.TABNAME,
               R.CONSTNAME,
               R.REFTABNAME,
               R.UPDATERULE,
               R.DELETERULE,
               PKCU.COLNAME,
               FKCU.COLNAME
        FROM SYSCAT.REFERENCES AS R
                 JOIN SYSCAT.TABLES AS T
                      ON T.TABSCHEMA = R.TABSCHEMA
                          AND T.TABNAME = R.TABNAME
                 JOIN SYSCAT.KEYCOLUSE AS PKCU
                      ON PKCU.CONSTNAME = R.CONSTNAME
                          AND PKCU.TABSCHEMA = R.TABSCHEMA
                          AND PKCU.TABNAME = R.TABNAME
                 JOIN SYSCAT.KEYCOLUSE AS FKCU
                      ON FKCU.CONSTNAME = R.REFKEYNAME
                          AND FKCU.TABSCHEMA = R.REFTABSCHEMA
                          AND FKCU.TABNAME = R.REFTABNAME
                          AND FKCU.COLSEQ = PKCU.COLSEQ
        WHERE %s
          AND T.TYPE = 'T'
        ORDER BY R.TABNAME,
                 R.CONSTNAME,
                 PKCU.COLSEQ
        SQL, $this->build_table_query_predicate('R', $table_name, $params));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Foreign_Key_Constraint_Column_Metadata_Row(referencingSchemaName: null, referencingTableName: $row[0], id: null, name: $row[1], referencedSchemaName: null, referencedTableName: $row[2], matchType: Match_Type::SIMPLE, onUpdateAction: self::REFERENTIAL_ACTIONS[$row[3]], onDeleteAction: self::REFERENTIAL_ACTIONS[$row[4]], isDeferrable: false, isDeferred: false, referencingColumnName: $row[5], referencedColumnName: $row[6]);
        }
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function get_table_options(?string $table_name): iterable
    {
        $params = [];
        $sql = sprintf(<<<'SQL'
        SELECT TABNAME,
               REMARKS
        FROM SYSCAT.TABLES T
        WHERE %s
          AND TYPE = 'T'
        ORDER BY TABNAME
        SQL, $this->build_table_query_predicate(null, $table_name, $params));
        foreach ($this->connection->iterate_numeric($sql, $params) as $row) {
            yield new Table_Metadata_Row(null, $row[0], ['comment' => $row[1]]);
        }
    }
    /**
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     *
     * @param ?non-empty-string $relation
     * @param list<string>      $params
     *
     * @return non-empty-string
     */
    private function build_table_query_predicate(?string $relation, ?string $table_name, array &$params): string
    {
        $qualifier = $relation !== null ? $relation . '.' : '';
        $conditions = [$qualifier . 'TABSCHEMA = CURRENT USER'];
        if ($table_name !== null) {
            $conditions[] = $qualifier . 'TABNAME = ?';
            $params[] = $table_name;
        }
        return implode(' AND ', $conditions);
    }
    /**
     * {@inheritDoc}
     *
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatviews
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     */
    public function get_all_views(): iterable
    {
        $sql = <<<'SQL'
        SELECT VIEWNAME,
               TEXT
        FROM SYSCAT.VIEWS
        WHERE VIEWSCHEMA = CURRENT USER
        ORDER BY VIEWNAME
        SQL;
        foreach ($this->connection->iterate_numeric($sql) as $row) {
            yield new View_Metadata_Row(null, ...$row);
        }
    }
    /** {@inheritDoc} */
    public function get_all_sequences(): iterable
    {
        throw Not_Supported::new(__METHOD__);
    }
}