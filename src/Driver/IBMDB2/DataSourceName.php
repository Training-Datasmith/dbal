<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use function implode;

use InvalidArgumentException;
use SensitiveParameter;

use function sprintf;
use function str_contains;

/**
 * Db2 DSN
 */
final readonly class DataSourceName
{
    private function __construct(
        #[SensitiveParameter]
        private string $string,
    ) {
    }

    public function toString(): string
    {
        return $this->string;
    }

    /**
     * Creates the object from an array representation
     *
     * @param array<string,mixed> $params
     */
    public static function fromArray(
        #[SensitiveParameter]
        array $params,
    ): self {
        $chunks = [];

        foreach ($params as $key => $value) {
            $chunks[] = sprintf('%s=%s', $key, $value);
        }

        return new self(implode(';', $chunks));
    }

    /**
     * Creates the object from the given DBAL connection parameters.
     *
     * @param array<string,mixed> $params
     */
    public static function fromConnectionParameters(#[SensitiveParameter]
        array $params, ): self
    {
        if (isset($params['dbname'])) {
            $dbname = (string) $params['dbname'];

            if (str_contains($dbname, '=') || str_contains($dbname, ';')) {
                throw new InvalidArgumentException(
                    'The "dbname" connection parameter must not contain "=" or ";" to prevent DSN injection.',
                );
            }
        }

        $dsnParams = [];

        foreach (
            [
                'host'     => 'HOSTNAME',
                'port'     => 'PORT',
                'protocol' => 'PROTOCOL',
                'dbname'   => 'DATABASE',
                'user'     => 'UID',
                'password' => 'PWD',
            ] as $dbalParam => $dsnParam
        ) {
            if (! isset($params[$dbalParam])) {
                continue;
            }

            $dsnParams[$dsnParam] = $params[$dbalParam];
        }

        return self::fromArray($dsnParams);
    }
}
