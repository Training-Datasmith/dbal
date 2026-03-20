<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\My_Sql\Exception_Converter;
use Doctrine\DBAL\Platforms\Abstract_My_Sql_Platform;
use Doctrine\DBAL\Platforms\Exception\Invalid_Platform_Version;
use Doctrine\DBAL\Platforms\Maria_Db1010platform;
use Doctrine\DBAL\Platforms\Maria_Db1052platform;
use Doctrine\DBAL\Platforms\Maria_Db1060platform;
use Doctrine\DBAL\Platforms\Maria_Db110700platform;
use Doctrine\DBAL\Platforms\Maria_Db_Platform;
use Doctrine\DBAL\Platforms\My_Sql80platform;
use Doctrine\DBAL\Platforms\My_Sql84platform;
use Doctrine\DBAL\Platforms\My_Sql_Platform;
use Doctrine\DBAL\Server_Version_Provider;
use Doctrine\Deprecations\Deprecation;
use function preg_match;
use function stripos;
use function version_compare;
/**
 * Abstract base implementation of the {@see Driver} interface for MySQL based drivers.
 */
abstract class Abstract_My_Sql_Driver implements Driver
{
    /**
     * {@inheritDoc}
     *
     * @throws InvalidPlatformVersion
     */
    public function get_database_platform(Server_Version_Provider $version_provider): Abstract_My_Sql_Platform
    {
        $version = $version_provider->get_server_version();
        if (stripos($version, 'mariadb') !== false) {
            $maria_db_version = $this->get_maria_db_mysql_version_number($version);
            if (version_compare($maria_db_version, '11.7.0', '>=')) {
                return new Maria_Db110700platform();
            }
            if (version_compare($maria_db_version, '10.10.0', '>=')) {
                return new Maria_Db1010platform();
            }
            if (version_compare($maria_db_version, '10.6.0', '>=')) {
                return new Maria_Db1060platform();
            }
            Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6343', 'Support for MariaDB < 10.6.0 is deprecated and will be removed in DBAL 5');
            if (version_compare($maria_db_version, '10.5.2', '>=')) {
                return new Maria_Db1052platform();
            }
            return new Maria_Db_Platform();
        }
        if (version_compare($version, '8.4.0', '>=')) {
            return new My_Sql84platform();
        }
        if (version_compare($version, '8.0.0', '>=')) {
            return new My_Sql80platform();
        }
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6343', 'Support for MySQL < 8 is deprecated and will be removed in DBAL 5');
        return new My_Sql_Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     *
     * @throws InvalidPlatformVersion
     */
    private function get_maria_db_mysql_version_number(string $version_string): string
    {
        if (preg_match('/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i', $version_string, $version_parts) !== 1) {
            throw Invalid_Platform_Version::new($version_string, '^(?:5\.5\.5-)?(mariadb-)?<major_version>.<minor_version>.<patch_version>');
        }
        return $version_parts['major'] . '.' . $version_parts['minor'] . '.' . $version_parts['patch'];
    }
}