<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;
/** @deprecated */
class Maria_Db117keywords extends Maria_Db_Keywords
{
    /**
     * {@inheritDoc}
     *
     * @link https://mariadb.com/docs/server/reference/sql-structure/sql-language-structure/reserved-words
     */
    protected function get_keywords(): array
    {
        $keywords = parent::get_keywords();
        // New Keywords and Reserved Words
        $keywords = array_merge($keywords, ['VECTOR']);
        return $keywords;
    }
}