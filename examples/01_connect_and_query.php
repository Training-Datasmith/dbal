<?php

declare(strict_types=1);

/**
 * Example 01 — Connect to SQLite and run basic queries.
 *
 * Demonstrates opening a connection, creating a table, inserting rows,
 * and fetching results using Doctrine DBAL.
 *
 * Run:  php examples/01_connect_and_query.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Parameter_Type;

// --- Open an in-memory SQLite connection -------------------------------------
$connection = Driver_Manager::get_connection(['driver' => 'pdo_sqlite', 'memory' => true]);

// --- Create a simple table ----------------------------------------------------
$connection->execute_statement('
    CREATE TABLE users (
        id   INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT    NOT NULL,
        age  INTEGER NOT NULL
    )
');

// --- Insert rows using named parameters and explicit types --------------------
$connection->execute_statement(
    'INSERT INTO users (name, age) VALUES (:name, :age)',
    ['name' => 'Alice', 'age' => 30],
    ['name' => Parameter_Type::STRING, 'age' => Parameter_Type::INTEGER],
);

$connection->execute_statement(
    'INSERT INTO users (name, age) VALUES (:name, :age)',
    ['name' => 'Bob', 'age' => 25],
    ['name' => Parameter_Type::STRING, 'age' => Parameter_Type::INTEGER],
);

$connection->execute_statement(
    'INSERT INTO users (name, age) VALUES (:name, :age)',
    ['name' => 'Charlie', 'age' => 35],
    ['name' => Parameter_Type::STRING, 'age' => Parameter_Type::INTEGER],
);

// --- Fetch all rows as associative arrays -------------------------------------
$result = $connection->execute_query('SELECT id, name, age FROM users ORDER BY age');

echo 'All users (ordered by age):' . PHP_EOL;
while ($row = $result->fetch_associative()) {
    echo sprintf('  [%d] %-10s age %d%s', $row['id'], $row['name'], $row['age'], PHP_EOL);
}

// --- Fetch a single scalar value ----------------------------------------------
$count = $connection->fetch_one('SELECT COUNT(*) FROM users');
echo PHP_EOL . 'Total users: ' . $count . PHP_EOL;  // 3

// --- Parameterised IN query ---------------------------------------------------
$names = ['Alice', 'Charlie'];
[$sql, $params, $types] = $connection->get_database_platform()->getInExpression(
    'name',
    $names
);
// Simpler approach via executeQuery with list expansion:
$result = $connection->execute_query(
    'SELECT name FROM users WHERE age > :min_age',
    ['min_age' => 28],
    ['min_age' => Parameter_Type::INTEGER],
);

echo PHP_EOL . 'Users older than 28:' . PHP_EOL;
foreach ($result->fetch_all_associative() as $row) {
    echo '  ' . $row['name'] . PHP_EOL;
}
