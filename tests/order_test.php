<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/order_functions.php';
$checks = 0;
function orderCheck(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function orderReject(callable $operation, string $type = InvalidArgumentException::class): void {
    try { $operation(); } catch (Throwable $exception) {
        orderCheck($exception instanceof $type, 'Expected ' . $type);
        return;
    }
    orderCheck(false, 'Expected rejection.');
}
$input = ['restaurant' => 'jamilah', 'order_date' => '2026-11-21', 'customer_name' => 'Test Customer', 'total' => '0.30'];
orderCheck(orderValidate($input)['total'] === 30, 'Money is exact.');
orderCheck(orderNumber(12) === 'FD-000012', 'Stable order number.');
orderCheck(orderValidate(array_replace($input, ['order_date' => '2024-02-29']))['date'] === '2024-02-29', 'Leap day accepted.');
foreach (['2026-02-29', '2026-04-31', '2026-1-1', '0000-01-01', [], '2026-11-21 12:00'] as $invalid) {
    orderReject(fn () => orderValidate(array_replace($input, ['order_date' => $invalid])));
}
foreach (['', [], "a\0", str_repeat('a', 101)] as $invalid) orderReject(fn () => orderValidate(array_replace($input, ['customer_name' => $invalid])));
foreach (['-1', '1.001', '1e2', [], '10000000'] as $invalid) orderReject(fn () => orderValidate(array_replace($input, ['total' => $invalid])));
orderReject(fn () => orderValidate(array_replace($input, ['restaurant' => 'unknown'])));
foreach (['0', '-1', [], '1 OR 1=1'] as $invalid) orderReject(fn () => orderPositiveInteger($invalid, 'order'));
$database = serviceDatabase();
try {
    $definition = $database->query('SHOW CREATE TABLE orders')->fetch_row()[1];
    // MariaDB temporary tables do not support foreign keys. Keep all columns,
    // unique keys and CHECK constraints; live HTTP tests exercise the real FKs.
    $definition = preg_replace('/^  CONSTRAINT `fk_orders_[^`]+` FOREIGN KEY[^\n]*\n/m', '', $definition);
    $database->query(str_replace('CREATE TABLE `orders`', 'CREATE TEMPORARY TABLE `orders`', $definition));
    orderCheck(orderList($database, '', 1)['count'] === 0, 'Empty list.');
    $key = hash('sha256', 'initial-order');
    $id = orderCreate($database, $input + ['status' => 'completed', 'created_by' => '9999'], 1, $key);
    $row = orderList($database, '', 1)['rows'][0];
    orderCheck($row['status'] === 'pending' && (int) $row['revision'] === 1, 'New order cannot forge final status.');
    orderCheck((int) $database->query('SELECT created_by FROM orders')->fetch_row()[0] === 1, 'Creator comes from authenticated identity.');
    orderReject(fn () => orderCreate($database, $input, 1, $key), DomainException::class);
    orderCheck(orderList($database, '', 1)['count'] === 1, 'Duplicate form creates no second row.');
    orderSetStatus($database, $id, 1, 'completed', 2);
    orderCheck(orderList($database, '', 1)['rows'][0]['status'] === 'completed', 'Complete pending order.');
    orderReject(fn () => orderSetStatus($database, $id, 1, 'cancelled', 2), DomainException::class);
    orderReject(fn () => orderSetStatus($database, $id, 2, 'cancelled', 2), DomainException::class);
    orderReject(fn () => orderSetStatus($database, $id, 2, 'pending', 2));
    orderReject(fn () => orderSetStatus($database, 99999999, 1, 'completed', 2), DomainException::class);
    $cancelId = orderCreate($database, $input, 1, hash('sha256', 'cancel-order'));
    orderSetStatus($database, $cancelId, 1, 'cancelled', 1);
    orderCheck(orderList($database, '', 1)['rows'][0]['status'] === 'cancelled', 'Cancel pending order.');
    for ($index = 0; $index < 25; $index++) {
        orderCreate($database, array_replace($input, ['restaurant' => 'lepak']), 1, hash('sha256', 'page-' . $index));
    }
    $page = orderList($database, '', 1);
    orderCheck(count($page['rows']) === 25 && $page['pages'] === 2 && $page['count'] === 27, 'Bounded pagination.');
    orderCheck(count(orderList($database, '', 2)['rows']) === 2, 'Second page.');
    orderCheck(orderList($database, '', 999)['page'] === 2, 'Clamp out-of-range page.');
    orderCheck(orderList($database, 'jamilah', 1)['count'] === 2, 'Restaurant filter.');
    orderCheck(orderList($database, 'greenview', 1)['count'] === 0, 'Empty restaurant filter.');
    orderReject(fn () => orderList($database, "lepak' OR 1=1", 1));
} finally { $database->close(); }
echo "Passed {$checks} order checks." . PHP_EOL;
