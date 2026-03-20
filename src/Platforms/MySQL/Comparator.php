<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\My_Sql;

use function array_diff_assoc;
use Doctrine\DBAL\Platforms\Abstract_My_Sql_Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Comparator_Config;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Table_Diff;
/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 *
 * @phpstan-import-type PlatformOptions from Column
 */
class Comparator extends Base_Comparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(Abstract_My_Sql_Platform $platform, private readonly Charset_Metadata_Provider $charset_metadata_provider, private readonly Collation_Metadata_Provider $collation_metadata_provider, private readonly Default_Table_Options $default_table_options, Comparator_Config $config = new Comparator_Config())
    {
        parent::__construct($platform, $config);
    }
    public function compare_tables(Table $old_table, Table $new_table): Table_Diff
    {
        return parent::compare_tables($this->normalize_table($old_table), $this->normalize_table($new_table));
    }
    private function normalize_table(Table $table): Table
    {
        $charset = $table->get_option('charset');
        $collation = $table->get_option('collation');
        if ($charset === null && $collation !== null) {
            $charset = $this->collation_metadata_provider->get_collation_charset($collation);
        } elseif ($charset !== null && $collation === null) {
            $collation = $this->charset_metadata_provider->get_default_charset_collation($charset);
        } elseif ($charset === null && $collation === null) {
            $charset = $this->default_table_options->get_charset();
            $collation = $this->default_table_options->get_collation();
        }
        $table_options = ['charset' => $charset, 'collation' => $collation];
        $table = clone $table;
        foreach ($table->get_columns() as $column) {
            $original_options = $column->get_platform_options();
            $normalized_options = $this->normalize_options($original_options);
            $override_options = array_diff_assoc($normalized_options, $table_options);
            if ($override_options === $original_options) {
                continue;
            }
            /** @phpstan-ignore argument.type */
            $column->set_platform_options($override_options);
        }
        return $table;
    }
    /**
     * @param PlatformOptions $options
     *
     * @return PlatformOptions
     */
    private function normalize_options(array $options): array
    {
        if (isset($options['charset']) && !isset($options['collation'])) {
            $options['collation'] = $this->charset_metadata_provider->get_default_charset_collation($options['charset']);
        } elseif (isset($options['collation']) && !isset($options['charset'])) {
            $options['charset'] = $this->collation_metadata_provider->get_collation_charset($options['collation']);
        }
        return $options;
    }
}