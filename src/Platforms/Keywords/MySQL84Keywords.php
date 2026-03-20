<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Keywords;

use function array_diff;
use function array_merge;
/**
 * MySQL 8.4 reserved keywords list.
 *
 * @deprecated
 */
class My_Sql84keywords extends My_Sql80keywords
{
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.4/en/keywords.html
     */
    protected function get_keywords(): array
    {
        $keywords = parent::get_keywords();
        // Removed Keywords and Reserved Words
        $keywords = array_diff($keywords, ['MASTER_BIND', 'MASTER_SSL_VERIFY_SERVER_CERT']);
        // New Keywords and Reserved Words
        $keywords = array_merge($keywords, ['AUTO', 'BERNOULLI', 'GTIDS', 'LOG', 'MANUAL', 'PARALLEL', 'PARSE_TREE', 'QUALIFY', 'S3', 'TABLESAMPLE']);
        return $keywords;
    }
}