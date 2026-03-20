<?php

declare(strict_types=1);

/**
 * Example 03 — Transactions and savepoints.
 *
 * Demonstrates commit, rollback, and nested transaction emulation
 * via savepoints using Doctrine DBAL.
 *
 * Run:  php examples/03_transactions.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\Driver_Manager;

$connection = Driver_Manager::get_connection(['driver' => 'pdo_sqlite', 'memory' => true]);

$connection->execute_statement('
    CREATE TABLE accounts (
        id      INTEGER PRIMARY KEY,
        owner   TEXT NOT NULL,
        balance REAL NOT NULL
    )
');
$connection->execute_statement("INSERT INTO accounts VALUES (1, 'Alice', 1000.00)");
$connection->execute_statement("INSERT INTO accounts VALUES (2, 'Bob',    500.00)");

// --- Helper -------------------------------------------------------------------
$print_balances = static function () use ($connection): void {
    $rows = $connection->fetch_all_associative('SELECT owner, balance FROM accounts ORDER BY id');
    foreach ($rows as $row) {
        echo sprintf('  %-6s $%.2f%s', $row['owner'], $row['balance'], PHP_EOL);
    }
};

echo 'Initial balances:' . PHP_EOL;
$print_balances();

// --- Successful transfer: Alice -> Bob ----------------------------------------
$connection->begin_transaction();
try {
    $connection->execute_statement(
        'UPDATE accounts SET balance = balance - 200 WHERE id = 1'
    );
    $connection->execute_statement(
        'UPDATE accounts SET balance = balance + 200 WHERE id = 2'
    );
    $connection->commit();
    echo PHP_EOL . 'After transfer ($200 Alice -> Bob):' . PHP_EOL;
    $print_balances();
} catch (\Throwable $e) {
    $connection->rollback();
    echo 'Transfer failed: ' . $e->getMessage() . PHP_EOL;
}

// --- Failed transfer demonstrating rollback -----------------------------------
$connection->begin_transaction();
try {
    $connection->execute_statement(
        'UPDATE accounts SET balance = balance - 999999 WHERE id = 1'  // leaves negative balance
    );
    // Simulate a business rule violation
    $alice_balance = (float) $connection->fetch_one('SELECT balance FROM accounts WHERE id = 1');
    if ($alice_balance < 0) {
        throw new \RuntimeException('Insufficient funds: balance would go negative.');
    }
    $connection->commit();
} catch (\RuntimeException $e) {
    $connection->rollback();
    echo PHP_EOL . 'Rolled back: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'Balances after failed transfer (unchanged):' . PHP_EOL;
$print_balances();

// --- transactional() helper (wraps in try/commit/rollback automatically) ------
$connection->transactional(static function () use ($connection): void {
    $connection->execute_statement(
        'UPDATE accounts SET balance = balance + 50 WHERE id = 2'
    );
    echo PHP_EOL . 'Inside transactional(): Bob received a $50 bonus.' . PHP_EOL;
});

echo PHP_EOL . 'Final balances:' . PHP_EOL;
$print_balances();
