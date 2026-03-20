<?php

declare (strict_types=1);
namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\Abstract_Driver_Middleware;
use Psr\Log\Logger_Interface;
use Sensitive_Parameter;
final class Driver extends Abstract_Driver_Middleware
{
    /** @internal This driver can be only instantiated by its middleware. */
    public function __construct(Driver_Interface $driver, private readonly Logger_Interface $logger)
    {
        parent::__construct($driver);
    }
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[Sensitive_Parameter]
        array $params
    ): Connection
    {
        $this->logger->info('Connecting with parameters {params}', ['params' => $this->mask_password($params)]);
        return new Connection(parent::connect($params), $this->logger);
    }
    /**
     * @param array<string,mixed> $params Connection parameters
     *
     * @return array<string,mixed>
     */
    private function mask_password(
        #[Sensitive_Parameter]
        array $params
    ): array
    {
        if (isset($params['password'])) {
            $params['password'] = '<redacted>';
        }
        return $params;
    }
}