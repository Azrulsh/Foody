<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/restaurant_functions.php';
$checks = 0;
function restaurantCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function restaurantRejects(callable $operation, string $type = InvalidArgumentException::class): void
{
    try { $operation(); } catch (Throwable $exception) {
        restaurantCheck($exception instanceof $type, 'Expected ' . $type);
        return;
    }
    restaurantCheck(false, 'Expected rejection.');
}
$valid = ['name' => 'Restoran Jamilah', 'address' => "Jalan 1\r\nKuala Lumpur", 'phone' => '+60 (12) 345-6789'];
restaurantCheck(restaurantValidate($valid)['address'] === "Jalan 1\nKuala Lumpur", 'Normalize address line endings.');
restaurantCheck(restaurantValidate(['name' => '测试餐厅', 'address' => '', 'phone' => ''])['name'] === '测试餐厅', 'Unicode name; optional address and phone.');
restaurantCheck(restaurantValidate(array_replace($valid, ['phone' => '0123456789']))['phone'] === '0123456789', 'Preserve leading phone zero.');
foreach (['', [], str_repeat('a', 101), "Name\0"] as $invalid) {
    restaurantRejects(fn () => restaurantValidate(array_replace($valid, ['name' => $invalid])));
}
foreach ([[], str_repeat('a', 501), "Address\0", "\xFF"] as $invalid) {
    restaurantRejects(fn () => restaurantValidate(array_replace($valid, ['address' => $invalid])));
}
foreach ([[], 'abc', '<script>', '12345', str_repeat('1', 16), '123456 ext 2', '+123456;DROP'] as $invalid) {
    restaurantRejects(fn () => restaurantValidate(array_replace($valid, ['phone' => $invalid])));
}
foreach ([[], 'unknown', "jamilah' OR 1=1"] as $invalid) restaurantRejects(fn () => restaurantCode($invalid));
foreach (['0', '-1', '1e2', [], str_repeat('9', 16)] as $invalid) restaurantRejects(fn () => restaurantRevision($invalid));
restaurantCheck(restaurantRevision('12') === 12, 'Valid revision.');
$database = serviceDatabase();
try {
    // All edits are confined to a temporary table on this connection.
    $definition = $database->query('SHOW CREATE TABLE restaurants')->fetch_row()[1];
    $database->query(str_replace('CREATE TABLE `restaurants`', 'CREATE TEMPORARY TABLE `restaurants`', $definition));
    restaurantRejects(fn () => restaurantLoadAll($database), RuntimeException::class);
    foreach (array_reverse(SERVICE_RESTAURANTS) as $code) {
        $statement = $database->prepare('INSERT INTO restaurants (code, name) VALUES (?, ?)');
        $statement->bind_param('ss', $code, $code);
        $statement->execute();
        $statement->close();
    }
    restaurantCheck(array_keys(restaurantLoadAll($database)) === SERVICE_RESTAURANTS, 'Original restaurant order.');
    restaurantUpdate($database, 'jamilah', $valid, 1);
    $rows = restaurantLoadAll($database);
    restaurantCheck($rows['jamilah']['name'] === $valid['name'] && $rows['jamilah']['code'] === 'jamilah', 'Rename preserves identity.');
    restaurantCheck($rows['jamilah']['phone'] === $valid['phone'] && $rows['jamilah']['revision'] === 2, 'Save fields and advance revision.');
    restaurantCheck($rows['lepak']['name'] === 'lepak' && $rows['lepak']['revision'] === 1, 'Other restaurants unchanged.');
    restaurantRejects(fn () => restaurantUpdate($database, 'jamilah', array_replace($valid, ['name' => 'Stale']), 1), DomainException::class);
    restaurantCheck(restaurantLoadAll($database)['jamilah']['name'] === $valid['name'], 'Stale update cannot overwrite.');
    restaurantUpdate($database, 'jamilah', $valid, 2);
    restaurantCheck(restaurantLoadAll($database)['jamilah']['revision'] === 3, 'Saving unchanged fields is valid.');
    restaurantRejects(fn () => restaurantUpdate($database, 'unknown', $valid, 1));
    restaurantCheck(count(restaurantLoadAll($database)) === 3, 'No added or deleted restaurants.');
} finally { $database->close(); }
echo "Passed {$checks} restaurant checks." . PHP_EOL;
