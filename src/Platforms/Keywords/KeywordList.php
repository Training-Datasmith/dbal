<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Platforms\Keywords;

use function array_flip;
use function array_map;
use Doctrine\Deprecations\Deprecation;
use function strtoupper;
/**
 * Abstract interface for a SQL reserved keyword dictionary.
 *
 * @deprecated
 */
abstract class Keyword_List
{
    /** @var string[]|null */
    private ?array $keywords = null;
    public function __construct()
    {
        Deprecation::trigger_if_called_from_outside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/6607', '%s is deprecated.', static::class);
    }
    /**
     * Checks if the given word is a keyword of this dialect/vendor platform.
     */
    public function is_keyword(string $word): bool
    {
        if ($this->keywords === null) {
            $this->initialize_keywords();
        }
        return isset($this->keywords[strtoupper($word)]);
    }
    protected function initialize_keywords(): void
    {
        $this->keywords = array_flip(array_map(strtoupper(...), $this->get_keywords()));
    }
    /**
     * Returns the list of keywords.
     *
     * @return string[]
     */
    abstract protected function get_keywords(): array;
}