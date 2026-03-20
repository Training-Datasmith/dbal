<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Driver\Mysqli\Initializer;

use Doctrine\DBAL\Driver\Mysqli\Initializer;
use mysqli;
use Sensitive_Parameter;
final readonly class Secure implements Initializer
{
    public function __construct(
        #[Sensitive_Parameter]
        private string $key,
        private string $cert,
        private string $ca,
        private string $capath,
        private string $cipher
    )
    {
    }
    public function initialize(mysqli $connection): void
    {
        $connection->ssl_set($this->key, $this->cert, $this->ca, $this->capath, $this->cipher);
    }
}