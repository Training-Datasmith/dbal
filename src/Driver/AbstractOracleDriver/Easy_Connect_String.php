<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Abstract_Oracle_Driver;

use Doctrine\Deprecations\Deprecation;
use function implode;
use function is_array;
use function sprintf;
/**
 * Represents an Oracle Easy Connect string
 *
 * @link https://docs.oracle.com/database/121/NETAG/naming.htm
 */
final readonly class Easy_Connect_String implements \Stringable
{
    private function __construct(private string $string)
    {
    }
    public function __toString(): string
    {
        return $this->string;
    }
    /**
     * Creates the object from an array representation
     *
     * @param mixed[] $params
     */
    public static function from_array(array $params): self
    {
        return new self(self::render_params($params));
    }
    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param mixed[] $params
     */
    public static function from_connection_parameters(array $params): self
    {
        if (isset($params['connectstring'])) {
            return new self($params['connectstring']);
        }
        if (!isset($params['host'])) {
            return new self($params['dbname'] ?? '');
        }
        $connect_data = [];
        if (isset($params['service'])) {
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/7042', 'Using the "service" parameter to indicate that the value of the "dbname" parameter is the' . ' service name is deprecated. Use the "servicename" parameter instead.');
        }
        if (isset($params['servicename']) || isset($params['dbname'])) {
            $service_key = 'SID';
            if (isset($params['service']) || isset($params['servicename'])) {
                $service_key = 'SERVICE_NAME';
            }
            $service_name = $params['servicename'] ?? $params['dbname'];
            $connect_data[$service_key] = $service_name;
        }
        if (isset($params['instancename'])) {
            $connect_data['INSTANCE_NAME'] = $params['instancename'];
        }
        if (!empty($params['pooled'])) {
            $connect_data['SERVER'] = 'POOLED';
        }
        return self::from_array(['DESCRIPTION' => ['ADDRESS' => ['PROTOCOL' => $params['driverOptions']['protocol'] ?? 'TCP', 'HOST' => $params['host'], 'PORT' => $params['port'] ?? 1521], 'CONNECT_DATA' => $connect_data]]);
    }
    /** @param mixed[] $params */
    private static function render_params(array $params): string
    {
        $chunks = [];
        foreach ($params as $key => $value) {
            $string = self::render_value($value);
            if ($string === '') {
                continue;
            }
            $chunks[] = sprintf('(%s=%s)', $key, $string);
        }
        return implode('', $chunks);
    }
    private static function render_value(mixed $value): string
    {
        if (is_array($value)) {
            return self::render_params($value);
        }
        return (string) $value;
    }
}