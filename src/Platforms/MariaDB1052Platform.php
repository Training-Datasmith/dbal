<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table_Diff;
/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.5 database platform.
 *
 * @deprecated This class will be removed once support for MariaDB 10.4 is dropped.
 */
class Maria_Db1052platform extends Maria_Db_Platform
{
    /**
     * {@inheritDoc}
     */
    protected function get_pre_alter_table_rename_index_foreign_key_sql(Table_Diff $diff): array
    {
        return Abstract_My_Sql_Platform::get_pre_alter_table_rename_index_foreign_key_sql($diff);
    }
    /**
     * {@inheritDoc}
     */
    protected function get_post_alter_table_index_foreign_key_sql(Table_Diff $diff): array
    {
        return Abstract_My_Sql_Platform::get_post_alter_table_index_foreign_key_sql($diff);
    }
    /**
     * {@inheritDoc}
     */
    protected function get_rename_index_sql(string $old_index_name, Index $index, $table_name): array
    {
        return ['ALTER TABLE ' . $table_name . ' RENAME INDEX ' . $old_index_name . ' TO ' . $index->get_quoted_name($this)];
    }
}