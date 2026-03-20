<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\IBMDB2;

use function implode;
use InvalidArgumentException;
use Sensitive_Parameter;
use function sprintf;
use function str_contains;
/**
 * Db2 DSN
 */
final readonly class Data_Source_Name
{
    private function __construct(
        #[Sensitive_Parameter]
        private string $string
    )
    {
    }
    public function to_string(): string
    {
        return $this->string;
    }
    /**
     * Creates the object from an array representation
     *
     * @param array<string,mixed> $params
     */
    public static function from_array(
        #[Sensitive_Parameter]
        array $params
    ): self
    {
        $chunks = [];
        foreach ($params as $key => $value) {
            $chunks[] = sprintf('%s=%s', $key, $value);
        }
        return new self(implode(';', $chunks));
    }
    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param array<string,mixed> $params
     */
    public static function from_connection_parameters(
        #[Sensitive_Parameter]
        array $params
    ): self
    {
        if (isset($params['dbname'])) {
            $dbname = (string) $params['dbname'];
            if (str_contains($dbname, '=') || str_contains($dbname, ';')) {
                throw new InvalidArgumentException('The "dbname" connection parameter must not contain "=" or ";" to prevent DSN injection.');
            }
        }
        $dsn_params = [];
        foreach (['host' => 'HOSTNAME', 'port' => 'PORT', 'protocol' => 'PROTOCOL', 'dbname' => 'DATABASE', 'user' => 'UID', 'password' => 'PWD'] as $dbal_param => $dsn_param) {
            if (!isset($params[$dbal_param])) {
                continue;
            }
            $dsn_params[$dsn_param] = $params[$dbal_param];
        }
        return self::from_array($dsn_params);
    }
}