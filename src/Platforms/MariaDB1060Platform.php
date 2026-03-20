<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\SQL\Builder\Select_Sql_Builder;
/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.6 database platform.
 *
 * @deprecated This class will be removed once support for MariaDB 10.5 is dropped.
 */
class Maria_Db1060platform extends Maria_Db1052platform
{
    public function create_select_sql_builder(): Select_Sql_Builder
    {
        return Abstract_Platform::create_select_sql_builder();
    }
}