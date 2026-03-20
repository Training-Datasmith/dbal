<?php

declare(strict_types=1);

/**
 * Example 02 — Query Builder.
 *
 * Shows how to construct SELECT, INSERT, UPDATE, and DELETE statements
 * programmatically using DBAL's QueryBuilder.
 *
 * Run:  php examples/02_query_builder.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Parameter_Type;

$connection = Driver_Manager::get_connection(['driver' => 'pdo_sqlite', 'memory' => true]);

// --- Setup --------------------------------------------------------------------
$connection->execute_statement('
    CREATE TABLE products (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        name     TEXT    NOT NULL,
        category TEXT    NOT NULL,
        price    REAL    NOT NULL
    )
');

// --- INSERT via QueryBuilder --------------------------------------------------
$qb = $connection->create_query_builder();
foreach ([
    ['Laptop', 'Electronics', 1200.00],
    ['Phone', 'Electronics', 699.00],
    ['Desk', 'Furniture', 450.00],
    ['Chair', 'Furniture', 299.00],
] as [$name, $category, $price]) {
    $qb->insert('products')
       ->values([
           'name'     => $qb->create_named_parameter($name, Parameter_Type::STRING),
           'category' => $qb->create_named_parameter($category, Parameter_Type::STRING),
           'price'    => $qb->create_named_parameter($price),
       ])
       ->execute_statement();
}

// --- SELECT with WHERE + ORDER ------------------------------------------------
$qb = $connection->create_query_builder();
$result = $qb
    ->select('id', 'name', 'price')
    ->from('products')
    ->where($qb->expr()->eq('category', $qb->create_named_parameter('Electronics')))
    ->and_where($qb->expr()->lt('price', $qb->create_named_parameter(1000.0)))
    ->order_by('price', 'ASC')
    ->execute_query();

echo 'Electronics under $1000 (cheapest first):' . PHP_EOL;
foreach ($result->fetch_all_associative() as $row) {
    echo sprintf('  [%d] %-8s $%.2f%s', $row['id'], $row['name'], $row['price'], PHP_EOL);
}

// --- UPDATE -------------------------------------------------------------------
$qb = $connection->create_query_builder();
$affected = $qb
    ->update('products')
    ->set('price', $qb->create_named_parameter(649.00))
    ->where($qb->expr()->eq('name', $qb->create_named_parameter('Phone')))
    ->execute_statement();

echo PHP_EOL . 'Updated ' . $affected . ' row(s).' . PHP_EOL;

// --- Verify updated price -----------------------------------------------------
$new_price = $connection->fetch_one(
    'SELECT price FROM products WHERE name = ?',
    ['Phone']
);
echo 'New Phone price: $' . $new_price . PHP_EOL;  // $649.0

// --- DELETE -------------------------------------------------------------------
$qb = $connection->create_query_builder();
$qb->delete('products')
   ->where($qb->expr()->eq('category', $qb->create_named_parameter('Furniture')))
   ->execute_statement();

$remaining = $connection->fetch_one('SELECT COUNT(*) FROM products');
echo 'Remaining products after deleting Furniture: ' . $remaining . PHP_EOL;  // 2
