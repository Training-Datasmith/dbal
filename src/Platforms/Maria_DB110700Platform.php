<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\Keyword_List;
use Doctrine\DBAL\Platforms\Keywords\Maria_Db117keywords;
use Doctrine\Deprecations\Deprecation;
/**
 * Provides the behavior, features and SQL dialect of the MariaDB 11.7 database platform.
 *
 * @deprecated To be removed along with the keyword list feature.
 */
class Maria_Db110700platform extends Maria_Db1010platform
{
    /** @deprecated */
    protected function create_reserved_keywords_list(): Keyword_List
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6607', '%s is deprecated.', __METHOD__);
        return new Maria_Db117keywords();
    }
}