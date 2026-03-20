<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Sq_Lite3;

use Doctrine\DBAL\Driver\Abstract_Sq_Lite_Driver;
use Sensitive_Parameter;
use Sq_Lite3;
final class Driver extends Abstract_Sq_Lite_Driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $is_memory = $params['memory'] ?? false;
        if (isset($params['path'])) {
            if ($is_memory) {
                throw new Exception('Invalid connection settings: specifying both parameters "path" and "memory" is ambiguous.');
            }
            $filename = $params['path'];
        } elseif ($is_memory) {
            $filename = ':memory:';
        } else {
            throw new Exception('Invalid connection settings: specify either the "path" or the "memory" parameter for SQLite3.');
        }
        try {
            $connection = new Sq_Lite3($filename);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
        $connection->enable_exceptions(true);
        return new Connection($connection);
    }
}