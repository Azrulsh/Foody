<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/service_functions.php';

$checks = 0;
function reportCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

reportCheck(serviceAmountTable('report') === 'report_amounts', 'Report table is isolated.');
try {
    serviceAmountTable('report_amounts; DROP TABLE commission');
    reportCheck(false, 'Unknown table identifier must fail.');
} catch (InvalidArgumentException) {
    reportCheck(true, 'Reject SQL identifier injection.');
}

if (in_array('--database', $argv, true)) {
    $database = serviceDatabase();
    try {
        // Temporary tables shadow real tables on this connection only.
        foreach (['service', 'report'] as $module) {
            $table = serviceAmountTable($module);
            $definition = $database->query("SHOW CREATE TABLE {$table}")->fetch_row()[1];
            $database->query(str_replace("CREATE TABLE `{$table}`", "CREATE TEMPORARY TABLE `{$table}`", $definition));
        }
        $week = [10, 20, 30, 40, 50, 60, 70];
        serviceSaveAmounts($database, 'lepak', 'weekly', $week, 'report');
        reportCheck(serviceLoadAmounts($database, 'lepak', 'weekly', 'report') === $week, 'Restore saved report values.');
        reportCheck(serviceLoadAmounts($database, 'lepak', 'weekly') === array_fill(0, 7, 0), 'Reports never overwrite services.');
        reportCheck(serviceLoadAmounts($database, 'jamilah', 'weekly', 'report') === array_fill(0, 7, 0), 'Restaurant isolation.');
        reportCheck(serviceLoadAmounts($database, 'lepak', 'monthly', 'report') === array_fill(0, 4, 0), 'Period isolation.');
        reportCheck(serviceFormatMoney(array_sum(serviceLoadAmounts($database, 'lepak', 'weekly', 'report'))) === '2.80', 'Exact report sum.');
        serviceSaveAmounts($database, 'lepak', 'monthly', [100, 200, 300, 400], 'report');
        serviceSaveAmounts($database, 'lepak', 'monthly', [400, 300, 200, 100], 'report');
        reportCheck(serviceLoadAmounts($database, 'lepak', 'monthly', 'report') === [400, 300, 200, 100], 'Update monthly reports.');
        reportCheck((int) $database->query('SELECT COUNT(*) FROM report_amounts')->fetch_row()[0] === 11, 'Repeated saves do not duplicate rows.');
        try {
            serviceSaveAmounts($database, 'lepak', 'weekly', array_fill(0, 8, 1), 'report');
            reportCheck(false, 'An invalid slot must fail.');
        } catch (mysqli_sql_exception) {
            reportCheck(serviceLoadAmounts($database, 'lepak', 'weekly', 'report') === $week, 'Failed report save rolls back all values.');
        }
        foreach (['weekly' => 7, 'monthly' => 4] as $period => $slots) {
            $before = serviceLoadAmounts($database, 'lepak', $period, 'report');
            $version = serviceAmountsVersion($before, 'lepak', $period, 'report');
            $saved = array_fill(0, $slots, 321);
            serviceSaveAmounts($database, 'lepak', $period, $saved, 'report', $version);
            try {
                serviceSaveAmounts($database, 'lepak', $period, $before, 'report', $version);
                reportCheck(false, 'A stale report must fail.');
            } catch (DomainException) {
                reportCheck(serviceLoadAmounts($database, 'lepak', $period, 'report') === $saved, 'A stale report cannot overwrite newer amounts.');
            }
            reportCheck(serviceAmountsVersion($saved, 'lepak', $period, 'service') !== serviceAmountsVersion($saved, 'lepak', $period, 'report'), 'Versions are bound to their module.');
        }
    } finally {
        // Closing the connection also removes temporary tables on every failure path.
        $database->close();
    }
}

echo "Passed {$checks} report checks." . PHP_EOL;
