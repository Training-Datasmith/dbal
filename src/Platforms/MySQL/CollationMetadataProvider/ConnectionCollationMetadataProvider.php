<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql\Collation_Metadata_Provider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\My_Sql\Collation_Metadata_Provider;
/** @internal */
final readonly class Connection_Collation_Metadata_Provider implements Collation_Metadata_Provider
{
    public function __construct(private Connection $connection)
    {
    }
    /** @throws Exception */
    public function get_collation_charset(string $collation): ?string
    {
        $charset = $this->connection->fetch_one(<<<'SQL'
        SELECT CHARACTER_SET_NAME
        FROM information_schema.COLLATIONS
        WHERE COLLATION_NAME = ?;
        SQL, [$collation]);
        if ($charset !== false) {
            return $charset;
        }
        return null;
    }
}