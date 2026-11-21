<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/service_functions.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

check(serviceMoneyToCents('0') === 0, 'Zero is accepted.');
check(serviceMoneyToCents('1.2') === 120, 'One decimal place is supported.');
check(serviceMoneyToCents('9999999.99') === SERVICE_MAX_CENTS, 'Maximum is exact.');
check(serviceFormatMoney(serviceMoneyToCents('0.10') + serviceMoneyToCents('0.20')) === '0.30', 'Money arithmetic must be exact.');
check(serviceFormatMoney(SERVICE_MAX_CENTS * 7) === '69999999.93', 'Weekly total supports seven maximum amounts.');
foreach (['', '-1', '1.234', '1e2', 'NaN', '10000000', '01', ' 1', '1 ', '<script>', "0'); DROP TABLE commission;--", [], null, 1] as $invalid) {
    rejects(fn () => serviceMoneyToCents($invalid), 'Reject malformed money.');
}
rejects(fn () => serviceValidateAmounts(['1.00'], 7), 'Missing slots are rejected.');
rejects(fn () => serviceValidateAmounts(array_fill(0, 8, '1.00'), 7), 'Extra slots are rejected.');
rejects(fn () => serviceValidateAmounts(['wrong' => '1.00'], 1), 'Unexpected keys are rejected.');
rejects(fn () => serviceValidateAmounts('1.00', 7), 'A scalar payload is rejected.');
rejects(fn () => serviceValidateAmounts([['1.00']], 1), 'Nested inputs are rejected.');
check(serviceValidateAmounts(['0.10', '0.20', '1', '2', '3', '4', '5'], 7) === [10, 20, 100, 200, 300, 400, 500], 'Validate an entire week.');
check(serviceEscape('"<script>') === '&quot;&lt;script&gt;', 'Output is escaped.');

if (in_array('--database', $argv, true)) {
    $database = serviceDatabase();
    // Connection-local temporary table shadows the real table. Production rows
    // remain untouched, including when serviceSaveAmounts commits transactions.
    $tableDefinition = $database->query('SHOW CREATE TABLE service_amounts')->fetch_row()[1];
    $database->query(str_replace('CREATE TABLE `service_amounts`', 'CREATE TEMPORARY TABLE `service_amounts`', $tableDefinition));
    try {
        check(serviceLoadAmounts($database, 'jamilah', 'weekly') === array_fill(0, 7, 0), 'An empty week reads as zero.');
        $week = [10, 20, 100, 200, 300, 400, 500];
        serviceSaveAmounts($database, 'jamilah', 'weekly', $week);
        check(serviceLoadAmounts($database, 'jamilah', 'weekly') === $week, 'Saved amounts are restored.');
        check(serviceLoadAmounts($database, 'greenview', 'weekly') === array_fill(0, 7, 0), 'Restaurant data is isolated.');
        check(serviceLoadAmounts($database, 'jamilah', 'monthly') === array_fill(0, 4, 0), 'Weekly and monthly amounts are independent.');
        serviceSaveAmounts($database, 'jamilah', 'monthly', [1, 2, 3, 4]);
        check(serviceLoadAmounts($database, 'jamilah', 'monthly') === [1, 2, 3, 4], 'Monthly values persist.');
        serviceSaveAmounts($database, 'jamilah', 'weekly', array_fill(0, 7, SERVICE_MAX_CENTS));
        check(serviceLoadAmounts($database, 'jamilah', 'weekly') === array_fill(0, 7, SERVICE_MAX_CENTS), 'Existing values update without duplicate rows.');
        try {
            serviceSaveAmounts($database, 'jamilah', 'weekly', array_fill(0, 8, 99));
            check(false, 'Invalid eighth slot must fail.');
        } catch (mysqli_sql_exception) {
            check(serviceLoadAmounts($database, 'jamilah', 'weekly') === array_fill(0, 7, SERVICE_MAX_CENTS), 'Failed saves roll back every changed slot.');
        }
        check((int) $database->query('SELECT COUNT(*) FROM service_amounts')->fetch_row()[0] === 11, 'Repeated saves do not duplicate rows.');
    } finally {
        $database->query('DROP TEMPORARY TABLE service_amounts');
        $database->close();
    }
}

echo "Passed {$checks} service checks." . PHP_EOL;
