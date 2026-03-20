<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Abstract_Oracle_Driver;
use Doctrine\DBAL\Driver\OCI8\Exception\Connection_Failed;
use Doctrine\DBAL\Driver\OCI8\Exception\Invalid_Configuration;
use function oci_connect;
use function oci_new_connect;
use const OCI_NO_AUTO_COMMIT;
use function oci_pconnect;
use Sensitive_Parameter;
/**
 * A Doctrine DBAL driver for the Oracle OCI8 PHP extensions.
 */
final class Driver extends Abstract_Oracle_Driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $username = $params['user'] ?? '';
        $password = $params['password'] ?? '';
        $charset = $params['charset'] ?? '';
        $session_mode = $params['sessionMode'] ?? OCI_NO_AUTO_COMMIT;
        $connection_string = $this->get_easy_connect_string($params);
        $persistent = !empty($params['persistent']);
        $exclusive = !empty($params['driverOptions']['exclusive']);
        if ($persistent && $exclusive) {
            throw Invalid_Configuration::for_persistent_and_exclusive();
        }
        if ($persistent) {
            $connection = @oci_pconnect($username, $password, $connection_string, $charset, $session_mode);
        } elseif ($exclusive) {
            $connection = @oci_new_connect($username, $password, $connection_string, $charset, $session_mode);
        } else {
            $connection = @oci_connect($username, $password, $connection_string, $charset, $session_mode);
        }
        if ($connection === false) {
            throw Connection_Failed::new();
        }
        return new Connection($connection);
    }
}