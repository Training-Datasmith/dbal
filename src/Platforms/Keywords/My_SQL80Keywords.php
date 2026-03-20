<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;
/**
 * MySQL 8.0 reserved keywords list.
 *
 * @deprecated
 */
class My_Sql80keywords extends My_Sql_Keywords
{
    /**
     * {@inheritDoc}
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/keywords.html
     */
    protected function get_keywords(): array
    {
        $keywords = parent::get_keywords();
        return array_merge($keywords, ['ADMIN', 'ARRAY', 'CUBE', 'CUME_DIST', 'DENSE_RANK', 'EMPTY', 'EXCEPT', 'FIRST_VALUE', 'FUNCTION', 'GROUPING', 'GROUPS', 'JSON_TABLE', 'LAG', 'LAST_VALUE', 'LATERAL', 'LEAD', 'MEMBER', 'NTH_VALUE', 'NTILE', 'OF', 'OVER', 'PERCENT_RANK', 'PERSIST', 'PERSIST_ONLY', 'RANK', 'RECURSIVE', 'ROW', 'ROWS', 'ROW_NUMBER', 'SYSTEM', 'WINDOW']);
    }
}