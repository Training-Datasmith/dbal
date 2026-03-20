<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\Exception_Converter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\Postgre_Sql\Exception_Converter;
use Doctrine\DBAL\Platforms\Exception\Invalid_Platform_Version;
use Doctrine\DBAL\Platforms\Postgre_Sql120platform;
use Doctrine\DBAL\Platforms\Postgre_Sql_Platform;
use Doctrine\DBAL\Server_Version_Provider;
use Doctrine\Deprecations\Deprecation;
use function preg_match;
use function version_compare;
/**
 * Abstract base implementation of the {@see Driver} interface for PostgreSQL based drivers.
 */
abstract class Abstract_Postgre_Sql_Driver implements Driver
{
    public function get_database_platform(Server_Version_Provider $version_provider): Postgre_Sql_Platform
    {
        $version = $version_provider->get_server_version();
        if (preg_match('/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/', $version, $version_parts) !== 1) {
            throw Invalid_Platform_Version::new($version, '<major_version>.<minor_version>.<patch_version>');
        }
        $major_version = $version_parts['major'];
        $minor_version = $version_parts['minor'] ?? 0;
        $patch_version = $version_parts['patch'] ?? 0;
        $version = $major_version . '.' . $minor_version . '.' . $patch_version;
        if (version_compare($version, '12.0', '>=')) {
            return new Postgre_Sql120platform();
        }
        Deprecation::trigger('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6495', 'Support for Postgres < 12 is deprecated and will be removed in DBAL 5');
        return new Postgre_Sql_Platform();
    }
    public function get_exception_converter(): Exception_Converter_Interface
    {
        return new Exception_Converter();
    }
}