<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Pg_Sql;

use function assert;
use Doctrine\DBAL\Driver\Pg_Sql\Exception\Unknown_Parameter;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Parameter_Type;
use function is_resource;
use function ksort;
use function pg_escape_bytea;
use function pg_escape_identifier;
use function pg_get_result;
use function pg_last_error;
use function pg_query;
use function pg_result_error;
use function pg_send_execute;
use Pg_Sql\Connection as PgSqlConnection;
use function stream_get_contents;
final class Statement implements Statement_Interface
{
    /** @var array<int, mixed> */
    private array $parameters = [];
    /** @phpstan-var array<int, ParameterType> */
    private array $parameter_types = [];
    /** @param array<array-key, int> $parameterMap */
    public function __construct(private readonly Pg_Sql_Connection $connection, private readonly string $name, private readonly array $parameter_map)
    {
    }
    public function __destruct()
    {
        // @phpstan-ignore isset.initializedProperty
        if (!isset($this->connection)) {
            return;
        }
        @pg_query($this->connection, 'DEALLOCATE ' . pg_escape_identifier($this->connection, $this->name));
    }
    /** {@inheritDoc} */
    public function bind_value(int|string $param, mixed $value, Parameter_Type $type = Parameter_Type::STRING): void
    {
        if (!isset($this->parameter_map[$param])) {
            throw Unknown_Parameter::new((string) $param);
        }
        if ($value === null) {
            $type = Parameter_Type::NULL;
        }
        if ($type === Parameter_Type::BOOLEAN) {
            $this->parameters[$this->parameter_map[$param]] = (bool) $value === false ? 'f' : 't';
            $this->parameter_types[$this->parameter_map[$param]] = Parameter_Type::STRING;
        } else {
            $this->parameters[$this->parameter_map[$param]] = $value;
            $this->parameter_types[$this->parameter_map[$param]] = $type;
        }
    }
    /** {@inheritDoc} */
    public function execute(): Result
    {
        ksort($this->parameters);
        $escaped_parameters = [];
        foreach ($this->parameters as $parameter => $value) {
            $escaped_parameters[] = match ($this->parameter_types[$parameter]) {
                Parameter_Type::BINARY, Parameter_Type::LARGE_OBJECT => $value === null ? null : pg_escape_bytea($this->connection, is_resource($value) ? stream_get_contents($value) : $value),
                default => $value,
            };
        }
        if (@pg_send_execute($this->connection, $this->name, $escaped_parameters) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }
        $result = @pg_get_result($this->connection);
        assert($result !== false);
        if ((bool) pg_result_error($result)) {
            throw Exception::from_result($result);
        }
        return new Result($result);
    }
}