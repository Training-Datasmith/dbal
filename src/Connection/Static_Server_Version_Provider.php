<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Connection;

use Doctrine\DBAL\Server_Version_Provider;
/** @final */
class Static_Server_Version_Provider implements Server_Version_Provider
{
    public function __construct(private readonly string $version)
    {
    }
    public function get_server_version(): string
    {
        return $this->version;
    }
}