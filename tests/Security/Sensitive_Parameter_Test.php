<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Security;

use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Exception\Driver_Required;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

/**
 * Security regression tests ensuring that connection credentials are not leaked
 * in stack traces or exception messages.
 *
 * PHP 8.2 introduced #[SensitiveParameter] which causes the parameter value to be
 * replaced with a SensitiveParameterValue placeholder in backtraces. This test
 * suite validates that DBAL marks credential parameters appropriately.
 */
#[CoversClass(Driver_Manager::class)]
final class Sensitive_Parameter_Test extends TestCase
{
    /**
     * Verifies that a password supplied to DriverManager::getConnection() does not
     * appear in plain text in the stack trace when an exception is thrown.
     *
     * Without #[SensitiveParameter], a var_dump() or debug_backtrace() of the
     * exception's frame would expose the raw password string to logs or error handlers.
     * With the attribute the value is wrapped in SensitiveParameterValue and its
     * string representation is redacted.
     */
    public function test_password_does_not_appear_in_exception_backtrace(): void
    {
        $secret_password = 'super_secret_password_that_must_not_leak';

        $backtrace_frames = [];
        $exception = null;

        try {
            // Deliberately omit a required 'driver' key to trigger an exception
            // while a password is in scope — simulating a misconfigured connection.
            Driver_Manager::get_connection([
                // No 'driver' key — will throw DriverRequired
                'password' => $secret_password,
            ]);
        } catch (Driver_Required $e) {
            $exception = $e;
            $backtrace_frames = $e->getTrace();
        }

        self::assertNotNull($exception, 'Expected DriverRequired exception was not thrown.');

        // Scan every serialised frame for the raw secret.
        // If #[SensitiveParameter] is honoured, the password must NOT appear.
        $trace_as_string = serialize($backtrace_frames);
        self::assertStringNotContainsString(
            $secret_password,
            $trace_as_string,
            'The raw password was found in the exception backtrace. ' .
            'Ensure #[SensitiveParameter] is applied to credential parameters.'
        );
    }

    /**
     * Ensures that the exception message produced for a missing driver does NOT
     * echo back any connection parameters (which might include credentials).
     */
    public function test_driver_required_exception_message_does_not_contain_credentials(): void
    {
        $secret = 'do_not_expose_me';

        try {
            Driver_Manager::get_connection(['password' => $secret]);
            self::fail('Expected Driver_Required exception.');
        } catch (Driver_Required $e) {
            self::assertStringNotContainsString(
                $secret,
                $e->getMessage(),
                'Exception message must not contain raw credential values.'
            );
        }
    }

    /**
     * Validates that an invalid driver name in the params array does not reflect
     * the credential value back in the error output (defence-in-depth for the
     * parameter-expansion code path).
     */
    public function test_invalid_driver_exception_does_not_leak_password(): void
    {
        $password = 'secret_password_1234';

        try {
            Driver_Manager::get_connection([
                'driver'   => 'pdo_sqlite',
                'memory'   => true,
                'password' => $password,
                // Provoke an error by passing a bad wrapper class
                'wrapperClass' => 'NonExistentWrapperClass',
            ]);
            self::fail('Expected an exception about the invalid wrapper class.');
        } catch (\Throwable $e) {
            $full_output = $e->getMessage() . serialize($e->getTrace());
            self::assertStringNotContainsString(
                $password,
                $full_output,
                'Password must not appear in exception output even when a wrapper class error occurs.'
            );
        }
    }
}
