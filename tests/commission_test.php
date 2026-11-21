<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/commission_functions.php';

$checks = 0;
function commissionCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function commissionRejects(callable $operation, string $exceptionClass): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        commissionCheck($exception instanceof $exceptionClass, 'Expected ' . $exceptionClass);
        return;
    }
    commissionCheck(false, 'Operation must be rejected.');
}

$input = ['commissions' => '100', 'penalties' => '10', 'services' => '5', 'totals' => '999999'];
commissionCheck(commissionValidateAmounts($input)['nett'] === 8500, 'Agreed formula; ignore tampered NETT.');
commissionCheck(commissionFormatMoney(8500) === '85.00', 'Format positive NETT.');
commissionCheck(commissionFormatMoney(-50) === '-0.50', 'Format negative fractional NETT accurately.');
commissionCheck(commissionFormatMoney(0) === '0.00', 'Zero NETT.');
commissionCheck(commissionValidateAmounts(['commissions' => '0.30', 'penalties' => '0.10', 'services' => '0.20'])['nett'] === 0, 'Exact decimal subtraction.');
commissionCheck(commissionValidateAmounts(['commissions' => '0', 'penalties' => '1', 'services' => '2'])['nett'] === -300, 'A deficit is not silently clamped to zero.');
foreach (['-1', '1.001', '1e3', '', [], '10000000', "0'); DROP TABLE commission;--"] as $invalid) {
    commissionRejects(fn () => commissionValidateAmounts(['commissions' => $invalid, 'penalties' => '0', 'services' => '0']), InvalidArgumentException::class);
}
commissionRejects(fn () => commissionContext(['restaurant' => []]), InvalidArgumentException::class);
commissionRejects(fn () => commissionContext(['restaurant' => 'unknown']), InvalidArgumentException::class);
commissionRejects(fn () => commissionContext(['module' => 'unknown']), InvalidArgumentException::class);
commissionRejects(fn () => commissionContext(['period' => 'unknown']), InvalidArgumentException::class);

if (in_array('--database', $argv, true)) {
    $database = serviceDatabase();
    try {
        // All writes are confined to a connection-local temporary table.
        $definition = $database->query('SHOW CREATE TABLE commission_records')->fetch_row()[1];
        $database->query(str_replace('CREATE TABLE `commission_records`', 'CREATE TEMPORARY TABLE `commission_records`', $definition));
        $context = commissionContext([]);
        commissionCheck(commissionLoad($database, $context) === null, 'No fabricated commission record.');
        commissionRejects(fn () => commissionSave($database, $context, $input, 'update', 0), DomainException::class);
        commissionSave($database, $context, $input, 'add', 0);
        $record = commissionLoad($database, $context);
        commissionCheck($record['nett'] === 8500 && $record['revision'] === 1, 'Saved record matches server calculation.');
        commissionRejects(fn () => commissionSave($database, $context, $input, 'add', 0), DomainException::class);
        foreach ([['restaurant' => 'lepak'], ['module' => 'report'], ['period' => 'monthly']] as $different) {
            commissionCheck(commissionLoad($database, array_replace($context, $different)) === null, 'Scope isolation.');
        }
        $updated = ['commissions' => '90.25', 'penalties' => '1.10', 'services' => '2.05'];
        commissionSave($database, $context, $updated, 'update', 1);
        $record = commissionLoad($database, $context);
        commissionCheck($record['nett'] === 8710 && $record['revision'] === 2, 'Update advances revision and preserves cents.');
        commissionRejects(fn () => commissionSave($database, $context, $input, 'update', 1), DomainException::class);
        commissionCheck(commissionLoad($database, $context)['nett'] === 8710, 'Stale update cannot overwrite newer data.');
        commissionRejects(fn () => commissionSave($database, $context, $input, 'delete', 2), InvalidArgumentException::class);
        commissionCheck((int) $database->query('SELECT COUNT(*) FROM commission_records')->fetch_row()[0] === 1, 'Duplicate submissions do not duplicate records.');
    } finally {
        $database->close();
    }
}
echo "Passed {$checks} commission checks." . PHP_EOL;
