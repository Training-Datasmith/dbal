<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql\Charset_Metadata_Provider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\My_Sql\Charset_Metadata_Provider;
/** @internal */
final readonly class Connection_Charset_Metadata_Provider implements Charset_Metadata_Provider
{
    public function __construct(private Connection $connection)
    {
    }
    /** @throws Exception */
    public function get_default_charset_collation(string $charset): ?string
    {
        $collation = $this->connection->fetch_one(<<<'SQL'
        SELECT DEFAULT_COLLATE_NAME
        FROM information_schema.CHARACTER_SETS
        WHERE CHARACTER_SET_NAME = ?;
        SQL, [$charset]);
        if ($collation !== false) {
            return $collation;
        }
        return null;
    }
}