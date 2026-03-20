<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use function addcslashes;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\Invalid_Column_Declaration;
use Doctrine\DBAL\Exception\Invalid_Column_Type;
use Doctrine\DBAL\Exception\Invalid_Column_Type\Column_Length_Required;
use Doctrine\DBAL\Exception\Invalid_Column_Type\Column_Precision_Required;
use Doctrine\DBAL\Exception\Invalid_Column_Type\Column_Scale_Required;
use Doctrine\DBAL\Exception\Invalid_Column_Type\Column_Values_Required;
use Doctrine\DBAL\Lock_Mode;
use Doctrine\DBAL\Platforms\Exception\No_Columns_Specified_For_Table;
use Doctrine\DBAL\Platforms\Exception\Not_Supported;
use Doctrine\DBAL\Platforms\Keywords\Keyword_List;
use Doctrine\DBAL\Schema\Abstract_Schema_Manager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Default_Expression;
use Doctrine\DBAL\Schema\Foreign_Key_Constraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Metadata\Metadata_Provider;
use Doctrine\DBAL\Schema\Name\Unquoted_Identifier_Folding;
use Doctrine\DBAL\Schema\Schema_Diff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Table_Diff;
use Doctrine\DBAL\Schema\Unique_Constraint;
use Doctrine\DBAL\SQL\Builder\Default_Select_Sql_Builder;
use Doctrine\DBAL\SQL\Builder\Default_Union_Sql_Builder;
use Doctrine\DBAL\SQL\Builder\Select_Sql_Builder;
use Doctrine\DBAL\SQL\Builder\Union_Sql_Builder;
use Doctrine\DBAL\SQL\Builder\With_Sql_Builder;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Transaction_Isolation_Level;
use Doctrine\DBAL\Types;
use Doctrine\DBAL\Types\Exception\Type_Not_Found;
use Doctrine\DBAL\Types\Exception\Types_Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function key;
use function max;
use function mb_strlen;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function strtoupper;
/**
 * Base class for all DatabasePlatforms. The DatabasePlatforms are the central
 * point of abstraction of platform-specific behaviors, features and SQL dialects.
 * They are a passive source of information.
 *
 * @phpstan-import-type ColumnProperties from Column
 * @phpstan-type CreateTableParameters = array{
 *    primary?: list<string>,
 *    primary_index?: Index,
 *    indexes?: list<Index>,
 *    uniqueConstraints?: list<UniqueConstraint>,
 *    foreignKeys?: list<ForeignKeyConstraint>,
 *    comment?: string,
 * }
 */
abstract class Abstract_Platform
{
    /** @deprecated */
    public const CREATE_INDEXES = 1;
    /** @deprecated */
    public const CREATE_FOREIGNKEYS = 2;
    /** @var string[]|null */
    protected ?array $doctrine_type_mapping = null;
    /**
     * Holds the KeywordList instance for the current platform.
     *
     * @deprecated
     */
    protected ?Keyword_List $_keywords = null;
    /**
     * Defines how the platform folds the case of unquoted identifiers.
     */
    private ?Unquoted_Identifier_Folding $unquoted_identifier_folding = null;
    public function __construct(?Unquoted_Identifier_Folding $unquoted_identifier_folding = null)
    {
        if ($unquoted_identifier_folding === null) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6823', 'Not passing $unquotedIdentifierFolding to %s() is deprecated.', __METHOD__);
        }
        $this->unquoted_identifier_folding = $unquoted_identifier_folding ?? Unquoted_Identifier_Folding::UPPER;
    }
    /**
     * Returns the SQL snippet that declares a boolean column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_boolean_type_declaration_sql(array $column): string;
    /**
     * Returns the SQL snippet that declares a 4 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_integer_type_declaration_sql(array $column): string;
    /**
     * Returns the SQL snippet that declares an 8 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_big_int_type_declaration_sql(array $column): string;
    /**
     * Returns the SQL snippet that declares a 2 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_small_int_type_declaration_sql(array $column): string;
    /**
     * Returns the SQL snippet that declares common properties of an integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract protected function _get_common_integer_type_declaration_sql(array $column): string;
    /**
     * Lazy load Doctrine Type Mappings.
     */
    abstract protected function initialize_doctrine_type_mappings(): void;
    /**
     * Initializes Doctrine Type Mappings with the platform defaults
     * and with all additional type mappings.
     *
     * @throws TypesException
     */
    private function initialize_all_doctrine_type_mappings(): void
    {
        $this->initialize_doctrine_type_mappings();
        foreach (Type::get_types_map() as $type_name => $class_name) {
            foreach (Type::get_type($type_name)->get_mapped_database_types($this) as $db_type) {
                $db_type = strtolower($db_type);
                $this->doctrine_type_mapping[$db_type] = $type_name;
            }
        }
    }
    /**
     * Returns the SQL snippet used to declare a column that can
     * store characters in the ASCII character set
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function get_ascii_string_type_declaration_sql(array $column): string
    {
        return $this->get_string_type_declaration_sql($column);
    }
    /**
     * Returns the SQL snippet used to declare a string column type.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function get_string_type_declaration_sql(array $column): string
    {
        $length = $column['length'] ?? null;
        if (empty($column['fixed'])) {
            try {
                return $this->get_varchar_type_declaration_sql_snippet($length);
            } catch (Invalid_Column_Type $e) {
                throw Invalid_Column_Declaration::from_invalid_column_type($column['name'], $e);
            }
        }
        return $this->get_char_type_declaration_sql_snippet($length);
    }
    /**
     * Returns the SQL snippet used to declare a binary string column type.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function get_binary_type_declaration_sql(array $column): string
    {
        $length = $column['length'] ?? null;
        try {
            if (empty($column['fixed'])) {
                return $this->get_varbinary_type_declaration_sql_snippet($length);
            }
            return $this->get_binary_type_declaration_sql_snippet($length);
        } catch (Invalid_Column_Type $e) {
            throw Invalid_Column_Declaration::from_invalid_column_type($column['name'], $e);
        }
    }
    /**
     * Returns the SQL snippet to declare an ENUM column.
     *
     * Enum is a non-standard type that is especially popular in MySQL and MariaDB. By default, this method map to
     * a simple VARCHAR field which allows us to deploy it on any platform, e.g. SQLite.
     *
     * @param array<string, mixed> $column
     *
     * @throws ColumnValuesRequired If the column definition does not contain any values.
     */
    public function get_enum_declaration_sql(array $column): string
    {
        if (!isset($column['values']) || !is_array($column['values']) || $column['values'] === []) {
            throw Column_Values_Required::new($this, 'ENUM');
        }
        $length = count($column['values']) > 1 ? max(...array_map(mb_strlen(...), $column['values'])) : mb_strlen((string) $column['values'][key($column['values'])]);
        if (isset($column['length'])) {
            if ($length > $column['length']) {
                throw new InvalidArgumentException(sprintf('Specified column length (%d) is less than the maximum length of provided values (%d).', $column['length'], $length));
            }
            $length = $column['length'];
        }
        return $this->get_string_type_declaration_sql(['length' => $length]);
    }
    /**
     * Returns the SQL snippet to declare a GUID/UUID column.
     *
     * By default this maps directly to a CHAR(36) and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function get_guid_type_declaration_sql(array $column): string
    {
        $column['length'] = 36;
        $column['fixed'] = true;
        return $this->get_string_type_declaration_sql($column);
    }
    /**
     * Returns the SQL snippet to declare a JSON column.
     *
     * By default this maps directly to a CLOB and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array<string, mixed> $column
     */
    public function get_json_type_declaration_sql(array $column): string
    {
        if (!empty($column['jsonb'])) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6939', 'The "jsonb" column platform option is deprecated. Use the "JSONB" type instead.');
        }
        return $this->get_clob_type_declaration_sql($column);
    }
    /**
     * Returns the SQL snippet to declare a JSONB column.
     *
     * @param array<string, mixed> $column
     */
    public function get_jsonb_type_declaration_sql(array $column): string
    {
        return $this->get_json_type_declaration_sql($column);
    }
    /**
     * @param int|null $length The length of the column in characters
     *                         or NULL if the length should be omitted.
     */
    protected function get_char_type_declaration_sql_snippet(?int $length): string
    {
        $sql = 'CHAR';
        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }
        return $sql;
    }
    /**
     * @param int|null $length The length of the column in characters
     *                         or NULL if the length should be omitted.
     */
    protected function get_varchar_type_declaration_sql_snippet(?int $length): string
    {
        if ($length === null) {
            throw Column_Length_Required::new($this, 'VARCHAR');
        }
        return sprintf('VARCHAR(%d)', $length);
    }
    /**
     * Returns the SQL snippet used to declare a fixed length binary column type.
     *
     * @param int|null $length The length of the column in bytes
     *                         or NULL if the length should be omitted.
     */
    protected function get_binary_type_declaration_sql_snippet(?int $length): string
    {
        $sql = 'BINARY';
        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }
        return $sql;
    }
    /**
     * Returns the SQL snippet used to declare a variable length binary column type.
     *
     * @param int|null $length The length of the column in bytes
     *                         or NULL if the length should be omitted.
     */
    protected function get_varbinary_type_declaration_sql_snippet(?int $length): string
    {
        if ($length === null) {
            throw Column_Length_Required::new($this, 'VARBINARY');
        }
        return sprintf('VARBINARY(%d)', $length);
    }
    /**
     * Returns the SQL snippet used to declare a CLOB column type.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_clob_type_declaration_sql(array $column): string;
    /**
     * Returns the SQL Snippet used to declare a BLOB column type.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_blob_type_declaration_sql(array $column): string;
    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @throws Exception If the type is not found.
     */
    public function register_doctrine_type_mapping(string $db_type, string $doctrine_type): void
    {
        if ($this->doctrine_type_mapping === null) {
            $this->initialize_all_doctrine_type_mappings();
        }
        if (!Types\Type::has_type($doctrine_type)) {
            throw Type_Not_Found::new($doctrine_type);
        }
        $db_type = strtolower($db_type);
        $this->doctrine_type_mapping[$db_type] = $doctrine_type;
    }
    /**
     * Gets the Doctrine type that is mapped for the given database column type.
     *
     * @throws TypesException
     */
    public function get_doctrine_type_mapping(string $db_type): string
    {
        if ($this->doctrine_type_mapping === null) {
            $this->initialize_all_doctrine_type_mappings();
        }
        $db_type = strtolower($db_type);
        if (!isset($this->doctrine_type_mapping[$db_type])) {
            throw new InvalidArgumentException(sprintf('Unknown database type "%s" requested, %s may not support it.', $db_type, static::class));
        }
        return $this->doctrine_type_mapping[$db_type];
    }
    /**
     * Checks if a database type is currently supported by this platform.
     *
     * @throws TypesException
     */
    public function has_doctrine_type_mapping_for(string $db_type): bool
    {
        if ($this->doctrine_type_mapping === null) {
            $this->initialize_all_doctrine_type_mappings();
        }
        $db_type = strtolower($db_type);
        return isset($this->doctrine_type_mapping[$db_type]);
    }
    /**
     * Returns the regular expression operator.
     */
    public function get_regexp_expression(): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Returns the SQL snippet to get the length of a text column in characters.
     *
     * @param string $string SQL expression producing the string.
     */
    public function get_length_expression(string $string): string
    {
        return 'LENGTH(' . $string . ')';
    }
    /**
     * Returns the SQL snippet to get the remainder of the operation of division of dividend by divisor.
     *
     * @param string $dividend SQL expression producing the dividend.
     * @param string $divisor  SQL expression producing the divisor.
     */
    public function get_mod_expression(string $dividend, string $divisor): string
    {
        return 'MOD(' . $dividend . ', ' . $divisor . ')';
    }
    /**
     * Returns the SQL snippet to trim a string.
     *
     * @param string      $str  The expression to apply the trim to.
     * @param TrimMode    $mode The position of the trim.
     * @param string|null $char The char to trim, has to be quoted already. Defaults to space.
     */
    public function get_trim_expression(string $str, Trim_Mode $mode = Trim_Mode::UNSPECIFIED, ?string $char = null): string
    {
        $tokens = [];
        switch ($mode) {
            case Trim_Mode::UNSPECIFIED:
                break;
            case Trim_Mode::LEADING:
                $tokens[] = 'LEADING';
                break;
            case Trim_Mode::TRAILING:
                $tokens[] = 'TRAILING';
                break;
            case Trim_Mode::BOTH:
                $tokens[] = 'BOTH';
                break;
        }
        if ($char !== null) {
            $tokens[] = $char;
        }
        if (count($tokens) > 0) {
            $tokens[] = 'FROM';
        }
        $tokens[] = $str;
        return sprintf('TRIM(%s)', implode(' ', $tokens));
    }
    /**
     * Returns the SQL snippet to get the position of the first occurrence of the substring in the string.
     *
     * @param string      $string    SQL expression producing the string to locate the substring in.
     * @param string      $substring SQL expression producing the substring to locate.
     * @param string|null $start     SQL expression producing the position to start at.
     *                               Defaults to the beginning of the string.
     */
    abstract public function get_locate_expression(string $string, string $substring, ?string $start = null): string;
    /**
     * Returns an SQL snippet to get a substring inside the string.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string      $string SQL expression producing the string from which a substring should be extracted.
     * @param string      $start  SQL expression producing the position to start at,
     * @param string|null $length SQL expression producing the length of the substring portion to be returned.
     *                            By default, the entire substring is returned.
     */
    public function get_substring_expression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s FROM %s)', $string, $start);
        }
        return sprintf('SUBSTRING(%s FROM %s FOR %s)', $string, $start, $length);
    }
    /**
     * Returns a SQL snippet to concatenate the given strings.
     */
    public function get_concat_expression(string ...$string): string
    {
        return implode(' || ', $string);
    }
    /**
     * Returns the SQL to calculate the difference in days between the two passed dates.
     *
     * Computes diff = date1 - date2.
     */
    abstract public function get_date_diff_expression(string $date1, string $date2): string;
    /**
     * Returns the SQL to add the number of given seconds to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     */
    public function get_date_add_seconds_expression(string $date, string $seconds): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $seconds, Date_Interval_Unit::SECOND);
    }
    /**
     * Returns the SQL to subtract the number of given seconds from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     */
    public function get_date_sub_seconds_expression(string $date, string $seconds): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $seconds, Date_Interval_Unit::SECOND);
    }
    /**
     * Returns the SQL to add the number of given minutes to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     */
    public function get_date_add_minutes_expression(string $date, string $minutes): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $minutes, Date_Interval_Unit::MINUTE);
    }
    /**
     * Returns the SQL to subtract the number of given minutes from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     */
    public function get_date_sub_minutes_expression(string $date, string $minutes): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $minutes, Date_Interval_Unit::MINUTE);
    }
    /**
     * Returns the SQL to add the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     */
    public function get_date_add_hour_expression(string $date, string $hours): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $hours, Date_Interval_Unit::HOUR);
    }
    /**
     * Returns the SQL to subtract the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     */
    public function get_date_sub_hour_expression(string $date, string $hours): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $hours, Date_Interval_Unit::HOUR);
    }
    /**
     * Returns the SQL to add the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     */
    public function get_date_add_days_expression(string $date, string $days): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $days, Date_Interval_Unit::DAY);
    }
    /**
     * Returns the SQL to subtract the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     */
    public function get_date_sub_days_expression(string $date, string $days): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $days, Date_Interval_Unit::DAY);
    }
    /**
     * Returns the SQL to add the number of given weeks to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     */
    public function get_date_add_weeks_expression(string $date, string $weeks): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $weeks, Date_Interval_Unit::WEEK);
    }
    /**
     * Returns the SQL to subtract the number of given weeks from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     */
    public function get_date_sub_weeks_expression(string $date, string $weeks): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $weeks, Date_Interval_Unit::WEEK);
    }
    /**
     * Returns the SQL to add the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     */
    public function get_date_add_month_expression(string $date, string $months): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $months, Date_Interval_Unit::MONTH);
    }
    /**
     * Returns the SQL to subtract the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     */
    public function get_date_sub_month_expression(string $date, string $months): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $months, Date_Interval_Unit::MONTH);
    }
    /**
     * Returns the SQL to add the number of given quarters to a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     */
    public function get_date_add_quarters_expression(string $date, string $quarters): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $quarters, Date_Interval_Unit::QUARTER);
    }
    /**
     * Returns the SQL to subtract the number of given quarters from a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     */
    public function get_date_sub_quarters_expression(string $date, string $quarters): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $quarters, Date_Interval_Unit::QUARTER);
    }
    /**
     * Returns the SQL to add the number of given years to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     */
    public function get_date_add_years_expression(string $date, string $years): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '+', $years, Date_Interval_Unit::YEAR);
    }
    /**
     * Returns the SQL to subtract the number of given years from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     */
    public function get_date_sub_years_expression(string $date, string $years): string
    {
        return $this->get_date_arithmetic_interval_expression($date, '-', $years, Date_Interval_Unit::YEAR);
    }
    /**
     * Returns the SQL for a date arithmetic expression.
     *
     * @param string           $date     SQL expression representing a date to perform the arithmetic operation on.
     * @param string           $operator The arithmetic operator (+ or -).
     * @param string           $interval SQL expression representing the value of the interval that shall be calculated
     *                                   into the date.
     * @param DateIntervalUnit $unit     The unit of the interval that shall be calculated into the date.
     */
    abstract protected function get_date_arithmetic_interval_expression(string $date, string $operator, string $interval, Date_Interval_Unit $unit): string;
    /**
     * Generates the SQL expression which represents the given date interval multiplied by a number
     *
     * @param string $interval   SQL expression describing the interval value
     * @param int    $multiplier Interval multiplier
     */
    protected function multiply_interval(string $interval, int $multiplier): string
    {
        return sprintf('(%s * %d)', $interval, $multiplier);
    }
    /**
     * Returns the SQL bit AND comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function get_bit_and_comparison_expression(string $value1, string $value2): string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }
    /**
     * Returns the SQL bit OR comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function get_bit_or_comparison_expression(string $value1, string $value2): string
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }
    /**
     * Returns the SQL expression which represents the currently selected database.
     */
    abstract public function get_current_database_expression(): string;
    /**
     * Honors that some SQL vendors such as MsSql use table hints for locking instead of the
     * ANSI SQL FOR UPDATE specification.
     *
     * @param string $fromClause The FROM clause to append the hint for the given lock mode to
     */
    public function append_lock_hint(string $from_clause, Lock_Mode $lock_mode): string
    {
        return $from_clause;
    }
    /**
     * Returns the SQL snippet to drop an existing table.
     */
    public function get_drop_table_sql(string $table): string
    {
        return 'DROP TABLE ' . $table;
    }
    /**
     * Returns the SQL to safely drop a temporary table WITHOUT implicitly committing an open transaction.
     */
    public function get_drop_temporary_table_sql(string $table): string
    {
        return $this->get_drop_table_sql($table);
    }
    /**
     * Returns the SQL to drop an index from a table.
     */
    public function get_drop_index_sql(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name;
    }
    /**
     * Returns the SQL to drop a constraint.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    protected function get_drop_constraint_sql(string $name, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $name;
    }
    /**
     * Returns the SQL to drop a foreign key.
     */
    public function get_drop_foreign_key_sql(string $foreign_key, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $foreign_key;
    }
    /**
     * Returns the SQL to drop a unique constraint.
     */
    public function get_drop_unique_constraint_sql(string $name, string $table_name): string
    {
        return $this->get_drop_constraint_sql($name, $table_name);
    }
    /**
     * Returns the SQL statement(s) to create a table with the specified name, columns and constraints
     * on this platform.
     *
     * @return list<string> The list of SQL statements.
     */
    public function get_create_table_sql(Table $table): array
    {
        return $this->build_create_table_sql($table, true);
    }
    public function create_select_sql_builder(): Select_Sql_Builder
    {
        return new Default_Select_Sql_Builder($this, 'FOR UPDATE', 'SKIP LOCKED');
    }
    public function create_union_sql_builder(): Union_Sql_Builder
    {
        return new Default_Union_Sql_Builder($this);
    }
    public function create_with_sql_builder(): With_Sql_Builder
    {
        return new With_Sql_Builder();
    }
    /**
     * @internal
     *
     * @return list<string>
     */
    final protected function get_create_table_without_foreign_keys_sql(Table $table): array
    {
        return $this->build_create_table_sql($table, false);
    }
    /** @return list<string> */
    private function build_create_table_sql(Table $table, bool $create_foreign_keys): array
    {
        if (count($table->get_columns()) === 0) {
            throw No_Columns_Specified_For_Table::new($table->get_name());
        }
        $table_name = $table->get_quoted_name($this);
        $options = $table->get_options();
        $options['primary'] = [];
        $options['indexes'] = [];
        $options['uniqueConstraints'] = [];
        $options['foreignKeys'] = [];
        foreach ($table->get_indexes() as $index) {
            if (!$index->is_primary()) {
                $options['indexes'][] = $index;
                continue;
            }
            $options['primary'] = $index->get_quoted_columns($this);
            $options['primary_index'] = $index;
        }
        foreach ($table->get_unique_constraints() as $unique_constraint) {
            $options['uniqueConstraints'][] = $unique_constraint;
        }
        if ($create_foreign_keys) {
            foreach ($table->get_foreign_keys() as $fk_constraint) {
                $options['foreignKeys'][] = $fk_constraint;
            }
        }
        $columns = [];
        foreach ($table->get_columns() as $column) {
            $columns[] = $this->column_to_array($column);
        }
        $sql = $this->_get_create_table_sql($table_name, $columns, $options);
        if ($this->supports_comment_on_statement()) {
            if ($table->has_option('comment')) {
                $sql[] = $this->get_comment_on_table_sql($table_name, $table->get_option('comment'));
            }
            foreach ($table->get_columns() as $column) {
                $comment = $column->get_comment();
                if ($comment === '') {
                    continue;
                }
                $sql[] = $this->get_comment_on_column_sql($table_name, $column->get_quoted_name($this), $comment);
            }
        }
        return $sql;
    }
    /**
     * @param array<Table> $tables
     *
     * @return list<string>
     */
    public function get_create_tables_sql(array $tables): array
    {
        $sql = [];
        foreach ($tables as $table) {
            $sql = array_merge($sql, $this->get_create_table_without_foreign_keys_sql($table));
        }
        foreach ($tables as $table) {
            foreach ($table->get_foreign_keys() as $foreign_key) {
                $sql[] = $this->get_create_foreign_key_sql($foreign_key, $table->get_quoted_name($this));
            }
        }
        return $sql;
    }
    /**
     * @param array<Table> $tables
     *
     * @return list<string>
     */
    public function get_drop_tables_sql(array $tables): array
    {
        $sql = [];
        foreach ($tables as $table) {
            foreach ($table->get_foreign_keys() as $foreign_key) {
                $sql[] = $this->get_drop_foreign_key_sql($foreign_key->get_quoted_name($this), $table->get_quoted_name($this));
            }
        }
        foreach ($tables as $table) {
            $sql[] = $this->get_drop_table_sql($table->get_quoted_name($this));
        }
        return $sql;
    }
    protected function get_comment_on_table_sql(string $table_name, string $comment): string
    {
        $table_name = new Identifier($table_name);
        return sprintf('COMMENT ON TABLE %s IS %s', $table_name->get_quoted_name($this), $this->quote_string_literal($comment));
    }
    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function get_comment_on_column_sql(string $table_name, string $column_name, string $comment): string
    {
        $table_name = new Identifier($table_name);
        $column_name = new Identifier($column_name);
        return sprintf('COMMENT ON COLUMN %s.%s IS %s', $table_name->get_quoted_name($this), $column_name->get_quoted_name($this), $this->quote_string_literal($comment));
    }
    /**
     * Returns the SQL to create inline comment on a column.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function get_inline_column_comment_sql(string $comment): string
    {
        if (!$this->supports_inline_column_comments()) {
            throw Not_Supported::new(__METHOD__);
        }
        return 'COMMENT ' . $this->quote_string_literal($comment);
    }
    /**
     * Returns the SQL used to create a table.
     *
     * @param list<ColumnProperties> $columns
     * @param CreateTableParameters  $options
     *
     * @return list<string>
     */
    protected function _get_create_table_sql(string $name, array $columns, array $options = []): array
    {
        $this->validate_create_table_options($options, __METHOD__);
        $column_list_sql = $this->get_column_declaration_list_sql($columns);
        foreach ($options['uniqueConstraints'] as $definition) {
            $column_list_sql .= ', ' . $this->get_unique_constraint_declaration_sql($definition);
        }
        if (!empty($options['primary'])) {
            $column_list_sql .= ', PRIMARY KEY (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }
        foreach ($options['indexes'] as $definition) {
            $column_list_sql .= ', ' . $this->get_index_declaration_sql($definition);
        }
        $query = 'CREATE TABLE ' . $name . ' (' . $column_list_sql;
        $check = $this->get_check_declaration_sql($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';
        $sql = [$query];
        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $definition) {
                $sql[] = $this->get_create_foreign_key_sql($definition, $name);
            }
        }
        return $sql;
    }
    /**
     * @internal
     *
     * @param CreateTableParameters $options
     */
    final protected function validate_create_table_options(array $options, string $method_name): void
    {
        if (isset($options['primary'], $options['indexes'], $options['uniqueConstraints'], $options['foreignKeys'])) {
            return;
        }
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6805', 'Not passing $options or any of its following keys to %s() is deprecated:' . ' "primary", "indexes", "uniqueConstraints", "foreignKeys".', $method_name);
    }
    public function get_create_temporary_table_snippet_sql(): string
    {
        return 'CREATE TEMPORARY TABLE';
    }
    /**
     * Generates SQL statements that can be used to apply the diff.
     *
     * @return list<string>
     */
    public function get_alter_schema_sql(Schema_Diff $diff): array
    {
        $sql = [];
        if ($this->supports_schemas()) {
            foreach ($diff->get_created_schemas() as $schema) {
                $sql[] = $this->get_create_schema_sql($schema);
            }
        }
        if ($this->supports_sequences()) {
            foreach ($diff->get_altered_sequences() as $sequence) {
                $sql[] = $this->get_alter_sequence_sql($sequence);
            }
            foreach ($diff->get_dropped_sequences() as $sequence) {
                $sql[] = $this->get_drop_sequence_sql($sequence->get_quoted_name($this));
            }
            foreach ($diff->get_created_sequences() as $sequence) {
                $sql[] = $this->get_create_sequence_sql($sequence);
            }
        }
        $sql = array_merge($sql, $this->get_create_tables_sql($diff->get_created_tables()), $this->get_drop_tables_sql($diff->get_dropped_tables()));
        foreach ($diff->get_altered_tables() as $table_diff) {
            $sql = array_merge($sql, $this->get_alter_table_sql($table_diff));
        }
        return $sql;
    }
    /**
     * Returns the SQL to create a sequence on this platform.
     */
    public function get_create_sequence_sql(Sequence $sequence): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Returns the SQL to change a sequence on this platform.
     */
    public function get_alter_sequence_sql(Sequence $sequence): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Returns the SQL snippet to drop an existing sequence.
     */
    public function get_drop_sequence_sql(string $name): string
    {
        if (!$this->supports_sequences()) {
            throw Not_Supported::new(__METHOD__);
        }
        return 'DROP SEQUENCE ' . $name;
    }
    /**
     * Returns the SQL to create an index on a table on this platform.
     */
    public function get_create_index_sql(Index $index, string $table): string
    {
        $name = $index->get_quoted_name($this);
        $columns = $index->get_columns();
        if (count($columns) === 0) {
            throw new InvalidArgumentException(sprintf('Incomplete or invalid index definition %s on table %s', $name, $table));
        }
        if ($index->is_primary()) {
            return $this->get_create_primary_key_sql($index, $table);
        }
        $query = 'CREATE ' . $this->get_create_index_sql_flags($index) . 'INDEX ' . $name . ' ON ' . $table;
        return $query . (' (' . implode(', ', $index->get_quoted_columns($this)) . ')' . $this->get_partial_index_sql($index));
    }
    /**
     * Adds condition for partial index.
     */
    protected function get_partial_index_sql(Index $index): string
    {
        if ($this->supports_partial_indexes() && $index->has_option('where')) {
            return ' WHERE ' . $index->get_option('where');
        }
        return '';
    }
    /**
     * Adds additional flags for index generation.
     */
    protected function get_create_index_sql_flags(Index $index): string
    {
        return $index->is_unique() ? 'UNIQUE ' : '';
    }
    /**
     * Returns the SQL to create an unnamed primary key constraint.
     *
     * @deprecated
     */
    public function get_create_primary_key_sql(Index $index, string $table): string
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6867', '%s() is deprecated.', __METHOD__);
        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . implode(', ', $index->get_quoted_columns($this)) . ')';
    }
    /**
     * Returns the SQL to create a named schema.
     */
    public function get_create_schema_sql(string $schema_name): string
    {
        if (!$this->supports_schemas()) {
            throw Not_Supported::new(__METHOD__);
        }
        return 'CREATE SCHEMA ' . $schema_name;
    }
    /**
     * Returns the SQL to create a unique constraint on a table on this platform.
     */
    public function get_create_unique_constraint_sql(Unique_Constraint $constraint, string $table_name): string
    {
        return 'ALTER TABLE ' . $table_name . ' ADD ' . $this->get_unique_constraint_declaration_sql($constraint);
    }
    /**
     * Returns the SQL snippet to drop a schema.
     */
    public function get_drop_schema_sql(string $schema_name): string
    {
        if (!$this->supports_schemas()) {
            throw Not_Supported::new(__METHOD__);
        }
        return 'DROP SCHEMA ' . $schema_name;
    }
    /**
     * Quotes a string so that it can be safely used as a table or column name,
     * even if it is a reserved word of the platform. This also detects identifier
     * chains separated by dot and quotes them independently.
     *
     * NOTE: Just because you CAN use quoted identifiers doesn't mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @deprecated Use {@link quoteSingleIdentifier()} individually for each part of a qualified name instead.
     *
     * @param string $identifier The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quote_identifier(string $identifier): string
    {
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6590', <<<'DEPRECATION'
        Method %s is deprecated and will be removed in 5.0.
        Use quoteSingleIdentifier() individually for each part of a qualified name instead.
        DEPRECATION, __METHOD__);
        if (str_contains($identifier, '.')) {
            $parts = array_map($this->quote_single_identifier(...), explode('.', $identifier));
            return implode('.', $parts);
        }
        return $this->quote_single_identifier($identifier);
    }
    /**
     * Quotes a single identifier (no dot chain separation).
     *
     * @param string $str The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quote_single_identifier(string $str): string
    {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    /**
     * Returns the SQL to create a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key constraint.
     * @param string               $table      The name of the table on which the foreign key is to be created.
     */
    public function get_create_foreign_key_sql(Foreign_Key_Constraint $foreign_key, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' ADD ' . $this->get_foreign_key_declaration_sql($foreign_key);
    }
    /**
     * Gets the SQL statements for altering an existing table.
     *
     * This method returns an array of SQL statements, since some platforms need several statements.
     *
     * @return list<string>
     */
    abstract public function get_alter_table_sql(Table_Diff $diff): array;
    public function get_rename_table_sql(string $old_name, string $new_name): string
    {
        return sprintf('ALTER TABLE %s RENAME TO %s', $old_name, $new_name);
    }
    /** @return list<string> */
    protected function get_pre_alter_table_index_foreign_key_sql(Table_Diff $diff): array
    {
        $table_name_sql = $diff->get_old_table()->get_quoted_name($this);
        $sql = [];
        foreach ($diff->get_dropped_foreign_keys() as $foreign_key) {
            $sql[] = $this->get_drop_foreign_key_sql($foreign_key->get_quoted_name($this), $table_name_sql);
        }
        foreach ($diff->get_modified_foreign_keys() as $foreign_key) {
            $sql[] = $this->get_drop_foreign_key_sql($foreign_key->get_quoted_name($this), $table_name_sql);
        }
        foreach ($diff->get_dropped_indexes() as $index) {
            $sql[] = $this->get_drop_index_sql($index->get_quoted_name($this), $table_name_sql);
        }
        foreach ($diff->get_modified_indexes() as $index) {
            $sql[] = $this->get_drop_index_sql($index->get_quoted_name($this), $table_name_sql);
        }
        return $sql;
    }
    /** @return list<string> */
    protected function get_post_alter_table_index_foreign_key_sql(Table_Diff $diff): array
    {
        $sql = [];
        $table_name_sql = $diff->get_old_table()->get_quoted_name($this);
        foreach ($diff->get_added_foreign_keys() as $foreign_key) {
            $sql[] = $this->get_create_foreign_key_sql($foreign_key, $table_name_sql);
        }
        foreach ($diff->get_modified_foreign_keys() as $foreign_key) {
            $sql[] = $this->get_create_foreign_key_sql($foreign_key, $table_name_sql);
        }
        foreach ($diff->get_added_indexes() as $index) {
            $sql[] = $this->get_create_index_sql($index, $table_name_sql);
        }
        foreach ($diff->get_modified_indexes() as $index) {
            $sql[] = $this->get_create_index_sql($index, $table_name_sql);
        }
        foreach ($diff->get_renamed_indexes() as $old_index_name => $index) {
            $old_index_name = new Identifier($old_index_name);
            $sql = array_merge($sql, $this->get_rename_index_sql($old_index_name->get_quoted_name($this), $index, $table_name_sql));
        }
        return $sql;
    }
    /**
     * Returns the SQL for renaming an index on a table.
     *
     * @param string $oldIndexName The name of the index to rename from.
     * @param Index  $index        The definition of the index to rename to.
     * @param string $tableName    The table to rename the given index on.
     *
     * @return list<string> The sequence of SQL statements for renaming the given index.
     */
    protected function get_rename_index_sql(string $old_index_name, Index $index, string $table_name): array
    {
        return [$this->get_drop_index_sql($old_index_name, $table_name), $this->get_create_index_sql($index, $table_name)];
    }
    /**
     * Returns the SQL for renaming a column
     *
     * @param string $tableName     The table to rename the column on.
     * @param string $oldColumnName The name of the column we want to rename.
     * @param string $newColumnName The name we should rename it to.
     *
     * @return list<string> The sequence of SQL statements for renaming the given column.
     */
    protected function get_rename_column_sql(string $table_name, string $old_column_name, string $new_column_name): array
    {
        return [sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $table_name, $old_column_name, $new_column_name)];
    }
    /**
     * Gets declaration of a number of columns in bulk.
     *
     * @param list<ColumnProperties> $columns The properties of the columns to be declared.
     */
    public function get_column_declaration_list_sql(array $columns): string
    {
        $declarations = [];
        foreach ($columns as $column) {
            $declarations[] = $this->get_column_declaration_sql($column['name'], $column);
        }
        return implode(', ', $declarations);
    }
    /**
     * Obtains DBMS specific SQL code portion needed to declare a generic type
     * column to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string           $name   The name of the column to be declared.
     * @param ColumnProperties $column Column properties.
     *
     * @return string DBMS specific SQL code portion that should be used to declare the column.
     */
    public function get_column_declaration_sql(string $name, array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $column['columnDefinition'];
        } else {
            $default = $this->get_default_value_declaration_sql($column);
            $charset = !empty($column['charset']) ? ' ' . $this->get_column_charset_declaration_sql($column['charset']) : '';
            $collation = !empty($column['collation']) ? ' ' . $this->get_column_collation_declaration_sql($column['collation']) : '';
            $notnull = !empty($column['notnull']) ? ' NOT NULL' : '';
            $type_decl = $column['type']->get_sql_declaration($column, $this);
            $declaration = $type_decl . $charset . $default . $notnull . $collation;
            if ($this->supports_inline_column_comments() && isset($column['comment']) && $column['comment'] !== '') {
                $declaration .= ' ' . $this->get_inline_column_comment_sql($column['comment']);
            }
        }
        return $name . ' ' . $declaration;
    }
    /**
     * Returns the SQL snippet that declares a floating point column of arbitrary precision.
     *
     * @param array<string, mixed> $column
     */
    public function get_decimal_type_declaration_sql(array $column): string
    {
        if (!isset($column['precision'])) {
            throw Invalid_Column_Declaration::from_invalid_column_type($column['name'], Column_Precision_Required::new());
        }
        if (!isset($column['scale'])) {
            throw Invalid_Column_Declaration::from_invalid_column_type($column['name'], Column_Scale_Required::new());
        }
        return 'NUMERIC(' . $column['precision'] . ', ' . $column['scale'] . ')';
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param array<string, mixed> $column The column definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    public function get_default_value_declaration_sql(array $column): string
    {
        if (!isset($column['default'])) {
            return empty($column['notnull']) ? ' DEFAULT NULL' : '';
        }
        $default = $column['default'];
        if ($default instanceof Default_Expression) {
            return ' DEFAULT ' . $default->to_sql($this);
        }
        if (!isset($column['type'])) {
            return " DEFAULT '" . $default . "'";
        }
        $type = $column['type'];
        if ($type instanceof Types\Php_Integer_Mapping_Type) {
            return ' DEFAULT ' . $default;
        }
        if ($type instanceof Types\Php_Date_Time_Mapping_Type && $default === $this->get_current_timestamp_sql()) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/7195', 'Using "%s" as a column default value is deprecated. Use a CurrentTimestamp instance instead.', $default);
            return ' DEFAULT ' . $this->get_current_timestamp_sql();
        }
        if ($type instanceof Types\Php_Time_Mapping_Type && $default === $this->get_current_time_sql()) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/7195', 'Using "%s" as a column default value is deprecated. Use a CurrentTime instance instead.', $default);
            return ' DEFAULT ' . $this->get_current_time_sql();
        }
        if ($type instanceof Types\Php_Date_Mapping_Type && $default === $this->get_current_date_sql()) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/7195', 'Using "%s" as a column default value is deprecated. Use a CurrentDate instance instead.', $default);
            return ' DEFAULT ' . $this->get_current_date_sql();
        }
        if ($type instanceof Types\Boolean_Type) {
            return ' DEFAULT ' . $this->convert_booleans($default);
        }
        if (is_int($default) || is_float($default)) {
            return ' DEFAULT ' . $default;
        }
        return ' DEFAULT ' . $this->quote_string_literal($default);
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set a CHECK constraint
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param list<string|ColumnProperties> $definition The check definition.
     *
     * @return string DBMS specific SQL code portion needed to set a CHECK constraint.
     */
    public function get_check_declaration_sql(array $definition): string
    {
        $constraints = [];
        foreach ($definition as $def) {
            if (is_string($def)) {
                Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6805', 'Passing column definition to %s() as string is deprecated. Pass the definition as array' . ' instead.', __METHOD__);
                $constraints[] = 'CHECK (' . $def . ')';
            } else {
                if (isset($def['min'])) {
                    $constraints[] = 'CHECK (' . $def['name'] . ' >= ' . $def['min'] . ')';
                }
                if (!isset($def['max'])) {
                    continue;
                }
                $constraints[] = 'CHECK (' . $def['name'] . ' <= ' . $def['max'] . ')';
            }
        }
        return implode(', ', $constraints);
    }
    /**
     * Obtains DBMS specific DDL fragment that defines a unique constraint to be used in statements like <code>CREATE
     * TABLE</code> or <code>ALTER TABLE</code>.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param UniqueConstraint $constraint The unique constraint definition.
     *
     * @return string DBMS specific DDL fragment that defines the constraint.
     */
    public function get_unique_constraint_declaration_sql(Unique_Constraint $constraint): string
    {
        $columns = $constraint->get_quoted_columns($this);
        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }
        $chunks = [];
        if ($constraint->get_name() !== '') {
            $chunks[] = 'CONSTRAINT';
            $chunks[] = $constraint->get_quoted_name($this);
        }
        $chunks[] = 'UNIQUE';
        if ($constraint->has_flag('clustered')) {
            $chunks[] = 'CLUSTERED';
        }
        $chunks[] = sprintf('(%s)', implode(', ', $columns));
        return implode(' ', $chunks);
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param Index $index The index definition.
     *
     * @return string DBMS specific SQL code portion needed to set an index.
     */
    public function get_index_declaration_sql(Index $index): string
    {
        $columns = $index->get_columns();
        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }
        return $this->get_create_index_sql_flags($index) . 'INDEX ' . $index->get_quoted_name($this) . ' (' . implode(', ', $index->get_quoted_columns($this)) . ')' . $this->get_partial_index_sql($index);
    }
    /**
     * Some vendors require temporary table names to be qualified specially.
     */
    public function get_temporary_table_name(string $table_name): string
    {
        return $table_name;
    }
    /**
     * Obtain DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @return string DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     *                of a column declaration.
     */
    public function get_foreign_key_declaration_sql(Foreign_Key_Constraint $foreign_key): string
    {
        $sql = $this->get_foreign_key_base_declaration_sql($foreign_key);
        return $sql . $this->get_advanced_foreign_key_options_sql($foreign_key);
    }
    /**
     * Returns the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key definition.
     */
    public function get_advanced_foreign_key_options_sql(Foreign_Key_Constraint $foreign_key): string
    {
        $query = '';
        if ($foreign_key->has_option('onUpdate')) {
            $query .= ' ON UPDATE ' . $this->get_foreign_key_referential_action_sql($foreign_key->get_option('onUpdate'));
        }
        if ($foreign_key->has_option('onDelete')) {
            $query .= ' ON DELETE ' . $this->get_foreign_key_referential_action_sql($foreign_key->get_option('onDelete'));
        }
        return $query;
    }
    /**
     * Returns the SQL fragment representing the deferrability of a constraint.
     */
    protected function get_constraint_deferrability_sql(Foreign_Key_Constraint $foreign_key): string
    {
        $sql = '';
        if ($foreign_key->has_option('deferrable')) {
            if ($foreign_key->get_option('deferrable') !== false) {
                $sql .= ' DEFERRABLE';
            } else {
                $sql .= ' NOT DEFERRABLE';
            }
        }
        if ($foreign_key->has_option('deferred')) {
            if ($foreign_key->get_option('deferred') !== false) {
                $sql .= ' INITIALLY DEFERRED';
            } else {
                $sql .= ' INITIALLY IMMEDIATE';
            }
        }
        return $sql;
    }
    /**
     * Returns the given referential action in uppercase if valid, otherwise throws an exception.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $action The foreign key referential action.
     */
    public function get_foreign_key_referential_action_sql(string $action): string
    {
        $upper = strtoupper($action);
        return match ($upper) {
            'CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT', 'SET DEFAULT' => $upper,
            default => throw new InvalidArgumentException(sprintf('Invalid foreign key action "%s".', $upper)),
        };
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function get_foreign_key_base_declaration_sql(Foreign_Key_Constraint $foreign_key): string
    {
        $sql = '';
        if ($foreign_key->get_name() !== '') {
            $sql .= 'CONSTRAINT ' . $foreign_key->get_quoted_name($this) . ' ';
        }
        $sql .= 'FOREIGN KEY (';
        if (count($foreign_key->get_local_columns()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "local" required.');
        }
        if (count($foreign_key->get_foreign_columns()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "foreign" required.');
        }
        if (strlen($foreign_key->get_foreign_table_name()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "foreignTable" required.');
        }
        return $sql . implode(', ', $foreign_key->get_quoted_local_columns($this)) . ') REFERENCES ' . $foreign_key->get_quoted_foreign_table_name($this) . ' (' . implode(', ', $foreign_key->get_quoted_foreign_columns($this)) . ')';
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $charset The name of the charset.
     *
     * @return string DBMS specific SQL code portion needed to set the CHARACTER SET
     *                of a column declaration.
     */
    public function get_column_charset_declaration_sql(string $charset): string
    {
        return '';
    }
    /**
     * Obtains DBMS specific SQL code portion needed to set the COLLATION
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $collation The name of the collation.
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a column declaration.
     */
    public function get_column_collation_declaration_sql(string $collation): string
    {
        return $this->supports_column_collation() ? 'COLLATE ' . $this->quote_single_identifier($collation) : '';
    }
    /**
     * Some platforms need the boolean values to be converted.
     *
     * The default conversion in this implementation converts to integers (false => 0, true => 1).
     *
     * Note: if the input is not a boolean the original input might be returned.
     *
     * There are two contexts when converting booleans: Literals and Prepared Statements.
     * This method should handle the literal case
     *
     * @param mixed $item A boolean or an array of them.
     *
     * @return mixed A boolean database value or an array of them.
     */
    public function convert_booleans(mixed $item): mixed
    {
        if (is_array($item)) {
            foreach ($item as $k => $value) {
                if (!is_bool($value)) {
                    continue;
                }
                $item[$k] = (int) $value;
            }
        } elseif (is_bool($item)) {
            $item = (int) $item;
        }
        return $item;
    }
    /**
     * Some platforms have boolean literals that needs to be correctly converted
     *
     * The default conversion tries to convert value into bool "(bool)$item"
     *
     * @param T $item
     *
     * @return (T is null ? null : bool)
     *
     * @template T
     */
    public function convert_from_boolean(mixed $item): ?bool
    {
        if ($item === null) {
            return null;
        }
        return (bool) $item;
    }
    /**
     * This method should handle the prepared statements case. When there is no
     * distinction, it's OK to use the same method.
     *
     * Note: if the input is not a boolean the original input might be returned.
     *
     * @param mixed $item A boolean or an array of them.
     *
     * @return mixed A boolean database value or an array of them.
     */
    public function convert_booleans_to_database_value(mixed $item): mixed
    {
        return $this->convert_booleans($item);
    }
    /**
     * Returns the SQL specific for the platform to get the current date.
     */
    public function get_current_date_sql(): string
    {
        return 'CURRENT_DATE';
    }
    /**
     * Returns the SQL specific for the platform to get the current time.
     */
    public function get_current_time_sql(): string
    {
        return 'CURRENT_TIME';
    }
    /**
     * Returns the SQL specific for the platform to get the current timestamp
     */
    public function get_current_timestamp_sql(): string
    {
        return 'CURRENT_TIMESTAMP';
    }
    /**
     * Returns the SQL for a given transaction isolation level Connection constant.
     */
    protected function _get_transaction_isolation_level_sql(Transaction_Isolation_Level $level): string
    {
        return match ($level) {
            Transaction_Isolation_Level::READ_UNCOMMITTED => 'READ UNCOMMITTED',
            Transaction_Isolation_Level::READ_COMMITTED => 'READ COMMITTED',
            Transaction_Isolation_Level::REPEATABLE_READ => 'REPEATABLE READ',
            Transaction_Isolation_Level::SERIALIZABLE => 'SERIALIZABLE',
        };
    }
    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function get_list_databases_sql(): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function get_list_sequences_sql(string $database): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Returns the SQL to list all views of a database or user.
     *
     * @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy.
     */
    abstract public function get_list_views_sql(string $database): string;
    public function get_create_view_sql(string $name, string $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }
    public function get_drop_view_sql(string $name): string
    {
        return 'DROP VIEW ' . $name;
    }
    public function get_sequence_next_val_sql(string $sequence): string
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Returns the SQL to create a new database.
     *
     * @param string $name The name of the database that should be created.
     */
    public function get_create_database_sql(string $name): string
    {
        return 'CREATE DATABASE ' . $name;
    }
    /**
     * Returns the SQL snippet to drop an existing database.
     *
     * @param string $name The name of the database that should be dropped.
     */
    public function get_drop_database_sql(string $name): string
    {
        return 'DROP DATABASE ' . $name;
    }
    /**
     * Returns the SQL to set the transaction isolation level.
     */
    abstract public function get_set_transaction_isolation_sql(Transaction_Isolation_Level $level): string;
    /**
     * Obtains DBMS specific SQL to be used to create datetime columns in
     * statements like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_date_time_type_declaration_sql(array $column): string;
    /**
     * Obtains DBMS specific SQL to be used to create datetime with timezone offset columns.
     *
     * @param array<string, mixed> $column
     */
    public function get_date_time_tz_type_declaration_sql(array $column): string
    {
        return $this->get_date_time_type_declaration_sql($column);
    }
    /**
     * Obtains DBMS specific SQL to be used to create date columns in statements
     * like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_date_type_declaration_sql(array $column): string;
    /**
     * Obtains DBMS specific SQL to be used to create time columns in statements
     * like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function get_time_type_declaration_sql(array $column): string;
    /** @param array<string, mixed> $column */
    public function get_float_declaration_sql(array $column): string
    {
        return 'DOUBLE PRECISION';
    }
    /** @param array<string, mixed> $column */
    public function get_small_float_declaration_sql(array $column): string
    {
        return 'REAL';
    }
    /**
     * Gets the default transaction isolation level of the platform.
     *
     * @return TransactionIsolationLevel The default isolation level.
     */
    public function get_default_transaction_isolation_level(): Transaction_Isolation_Level
    {
        return Transaction_Isolation_Level::READ_COMMITTED;
    }
    /* supports*() methods */
    /**
     * Whether the platform supports sequences.
     */
    public function supports_sequences(): bool
    {
        return false;
    }
    /**
     * Whether the platform supports identity columns.
     *
     * Identity columns are columns that receive an auto-generated value from the
     * database on insert of a row.
     */
    public function supports_identity_columns(): bool
    {
        return false;
    }
    /**
     * Whether the platform supports partial indexes.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supports_partial_indexes(): bool
    {
        return false;
    }
    /**
     * Whether the platform supports indexes with column length definitions.
     *
     * @deprecated
     */
    public function supports_column_length_indexes(): bool
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6886', '%s is deprecated.', __METHOD__);
        return false;
    }
    /**
     * Whether the platform supports savepoints.
     */
    public function supports_savepoints(): bool
    {
        return true;
    }
    /**
     * Whether the platform supports releasing savepoints.
     */
    public function supports_release_savepoints(): bool
    {
        return $this->supports_savepoints();
    }
    /**
     * Whether the platform supports database schemas.
     */
    public function supports_schemas(): bool
    {
        return false;
    }
    /**
     * Whether this platform support to add inline column comments as postfix.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supports_inline_column_comments(): bool
    {
        return false;
    }
    /**
     * Whether this platform support the proprietary syntax "COMMENT ON asset".
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supports_comment_on_statement(): bool
    {
        return false;
    }
    /**
     * Does this platform support column collation?
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supports_column_collation(): bool
    {
        return false;
    }
    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime value of this platform.
     *
     * @return string The format string.
     */
    public function get_date_time_format_string(): string
    {
        return 'Y-m-d H:i:s';
    }
    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime with timezone value of this platform.
     *
     * @return string The format string.
     */
    public function get_date_time_tz_format_string(): string
    {
        return 'Y-m-d H:i:s';
    }
    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored date value of this platform.
     *
     * @return string The format string.
     */
    public function get_date_format_string(): string
    {
        return 'Y-m-d';
    }
    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored time value of this platform.
     *
     * @return string The format string.
     */
    public function get_time_format_string(): string
    {
        return 'H:i:s';
    }
    /**
     * Adds an driver-specific LIMIT clause to the query.
     */
    final public function modify_limit_query(string $query, ?int $limit, int $offset = 0): string
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('Offset must be a positive integer or zero, %d given.', $offset));
        }
        return $this->do_modify_limit_query($query, $limit, $offset);
    }
    /**
     * Adds an platform-specific LIMIT clause to the query.
     */
    protected function do_modify_limit_query(string $query, ?int $limit, int $offset): string
    {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);
        }
        if ($offset > 0) {
            $query .= sprintf(' OFFSET %d', $offset);
        }
        return $query;
    }
    /**
     * Maximum length of any given database identifier, like tables or column names.
     *
     * @return positive-int
     */
    public function get_max_identifier_length(): int
    {
        return 63;
    }
    /**
     * Returns the insert SQL for an empty insert statement.
     */
    public function get_empty_identity_insert_sql(string $quoted_table_name, string $quoted_identifier_column_name): string
    {
        return 'INSERT INTO ' . $quoted_table_name . ' (' . $quoted_identifier_column_name . ') VALUES (null)';
    }
    /**
     * Generates a Truncate Table SQL statement for a given table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign keys.
     */
    public function get_truncate_table_sql(string $table_name, bool $cascade = false): string
    {
        $table_identifier = new Identifier($table_name);
        return 'TRUNCATE ' . $table_identifier->get_quoted_name($this);
    }
    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     */
    public function get_dummy_select_sql(string $expression = '1'): string
    {
        return sprintf('SELECT %s', $expression);
    }
    /**
     * Returns the SQL to create a new savepoint.
     */
    public function create_save_point(string $savepoint): string
    {
        return 'SAVEPOINT ' . $this->quote_single_identifier($savepoint);
    }
    /**
     * Returns the SQL to release a savepoint.
     */
    public function release_save_point(string $savepoint): string
    {
        return 'RELEASE SAVEPOINT ' . $this->quote_single_identifier($savepoint);
    }
    /**
     * Returns the SQL to rollback a savepoint.
     */
    public function rollback_save_point(string $savepoint): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $this->quote_single_identifier($savepoint);
    }
    /**
     * Returns the keyword list instance of this platform.
     *
     * @deprecated
     */
    final public function get_reserved_keywords_list(): Keyword_List
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6607', '%s is deprecated.', __METHOD__);
        // Store the instance so it doesn't need to be generated on every request.
        return $this->_keywords ??= $this->create_reserved_keywords_list();
    }
    /**
     * Creates an instance of the reserved keyword list of this platform.
     *
     * @deprecated
     */
    abstract protected function create_reserved_keywords_list(): Keyword_List;
    /**
     * Quotes a literal string.
     * This method is NOT meant to fix SQL injections!
     * It is only meant to escape this platform's string literal
     * quote character inside the given literal string.
     *
     * @param string $str The literal string to be quoted.
     *
     * @return string The quoted literal string.
     */
    public function quote_string_literal(string $str): string
    {
        return "'" . str_replace("'", "''", $str) . "'";
    }
    /**
     * Escapes metacharacters in a string intended to be used with a LIKE
     * operator.
     *
     * @param string $inputString a literal, unquoted string
     * @param string $escapeChar  should be reused by the caller in the LIKE
     *                            expression.
     */
    final public function escape_string_for_like(string $input_string, string $escape_char): string
    {
        $sql = preg_replace('~([' . preg_quote($this->get_like_wildcard_characters() . $escape_char, '~') . '])~u', addcslashes($escape_char, '\\') . '$1', $input_string);
        assert(is_string($sql));
        return $sql;
    }
    /**
     * @return ColumnProperties An associative array with the name of the properties of the column being declared as
     *                          array keys.
     */
    private function column_to_array(Column $column): array
    {
        return array_merge($column->to_array(), ['name' => $column->get_quoted_name($this), 'version' => $column->has_platform_option('version') ? $column->get_platform_option('version') : false, 'comment' => $column->get_comment()]);
    }
    /** @internal */
    public function create_sql_parser(): Parser
    {
        return new Parser(false);
    }
    protected function get_like_wildcard_characters(): string
    {
        return '%_';
    }
    /**
     * Compares the definitions of the given columns in the context of this platform.
     */
    public function columns_equal(Column $column1, Column $column2): bool
    {
        $column1Array = $this->column_to_array($column1);
        $column2Array = $this->column_to_array($column2);
        // ignore explicit columnDefinition since it's not set on the Column generated by the SchemaManager
        $column1Array['columnDefinition'] = null;
        $column2Array['columnDefinition'] = null;
        if ($this->get_column_declaration_sql('', $column1Array) !== $this->get_column_declaration_sql('', $column2Array)) {
            return false;
        }
        // If the platform supports inline comments, all comparison is already done above
        if ($this->supports_inline_column_comments()) {
            return true;
        }
        return $column1->get_comment() === $column2->get_comment();
    }
    /**
     * Returns the union select query part surrounded by parenthesis if possible for platform.
     */
    public function get_union_select_part_sql(string $sub_query): string
    {
        return sprintf('(%s)', $sub_query);
    }
    /**
     * Returns the `UNION ALL` keyword.
     */
    public function get_union_all_sql(): string
    {
        return 'UNION ALL';
    }
    /**
     * Returns the compatible `UNION DISTINCT` keyword.
     */
    public function get_union_distinct_sql(): string
    {
        return 'UNION';
    }
    public function get_unquoted_identifier_folding(): Unquoted_Identifier_Folding
    {
        if ($this->unquoted_identifier_folding === null) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6823', 'Not calling the %s constructor from child class constructors is deprecated.', self::class);
            $this->unquoted_identifier_folding = Unquoted_Identifier_Folding::UPPER;
        }
        return $this->unquoted_identifier_folding;
    }
    /**
     * Creates a metadata provider that can be used access the metadata of the underlying database schema.
     *
     * @throws Exception
     */
    public function create_metadata_provider(Connection $connection): Metadata_Provider
    {
        throw Not_Supported::new(__METHOD__);
    }
    /**
     * Creates the schema manager that can be used to inspect and change the underlying
     * database schema according to the dialect of the platform.
     */
    abstract public function create_schema_manager(Connection $connection): Abstract_Schema_Manager;
}