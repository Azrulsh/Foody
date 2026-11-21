<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/settings_functions.php';
$checks = 0;
function settingsCheck(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function settingsReject(callable $operation, string $type = InvalidArgumentException::class): void {
    try { $operation(); } catch (Throwable $exception) {
        settingsCheck($exception instanceof $type, 'Expected ' . $type);
        return;
    }
    settingsCheck(false, 'Expected rejection.');
}
$valid = ['default_restaurant' => 'lepak', 'default_duration' => 'monthly', 'orders_per_page' => '10', 'date_format' => 'd/m/Y', 'theme' => 'dark', 'panel_background' => 'solid'];
settingsCheck(settingsValidate($valid)['orders_per_page'] === 10, 'Normalize page size.');
foreach (['default_restaurant' => 'unknown', 'default_duration' => 'daily', 'orders_per_page' => '100000', 'date_format' => '<script>', 'theme' => '<script>', 'panel_background' => 'invalid'] as $key => $invalid) {
    settingsReject(fn () => settingsValidate(array_replace($valid, [$key => $invalid])));
    settingsReject(fn () => settingsValidate(array_replace($valid, [$key => []])));
}
settingsReject(fn () => settingsValidate([]));
foreach (['-1', '01', [], '1 OR 1=1'] as $invalid) settingsReject(fn () => settingsRevision($invalid));
settingsCheck(settingsRevision('0') === 0, 'Unsaved revision.');
settingsCheck(settingsFormatDate('2026-11-21', 'd/m/Y') === '21/11/2026', 'Date display.');
settingsCheck(settingsFormatDate('2026-11-21', 'Y-m-d') === '2026-11-21', 'ISO date display.');
settingsReject(fn () => settingsFormatDate('2026-11-21', 'unknown'));
settingsReject(fn () => settingsFormatDate('2026-02-29', 'Y-m-d'), RuntimeException::class);
$database = serviceDatabase();
try {
    $definition = $database->query('SHOW CREATE TABLE user_settings')->fetch_row()[1];
    $definition = preg_replace('/^  CONSTRAINT `fk_settings_user` FOREIGN KEY[^\n]*\n/m', '', $definition);
    $database->query(str_replace('CREATE TABLE `user_settings`', 'CREATE TEMPORARY TABLE `user_settings`', $definition));
    settingsCheck(settingsLoad($database, 1) === settingsDefaults(), 'No row uses defaults.');
    settingsSave($database, 1, $valid, 0);
    $saved = settingsLoad($database, 1);
    settingsCheck($saved['default_restaurant'] === 'lepak' && $saved['revision'] === 1, 'Persist first settings.');
    settingsCheck($saved['theme'] === 'dark' && $saved['panel_background'] === 'solid', 'Persist appearance.');
    settingsCheck(settingsLoad($database, 2) === settingsDefaults(), 'Another user is isolated.');
    settingsReject(fn () => settingsSave($database, 1, $valid, 0), DomainException::class);
    $reset = settingsDefaults();
    $reset['orders_per_page'] = (string) $reset['orders_per_page'];
    settingsSave($database, 1, $reset, 1);
    $restored = settingsLoad($database, 1);
    settingsCheck($restored['default_restaurant'] === '' && $restored['orders_per_page'] === 25 && $restored['revision'] === 2, 'Reset retains monotonic revision.');
    settingsCheck($restored['theme'] === 'light' && $restored['panel_background'] === 'transparent', 'Reset restores original appearance.');
    settingsReject(fn () => settingsSave($database, 1, $valid, 1), DomainException::class);
    settingsSave($database, 2, array_replace($valid, ['orders_per_page' => '50']), 0);
    settingsCheck(settingsLoad($database, 1)['orders_per_page'] === 25 && settingsLoad($database, 2)['orders_per_page'] === 50, 'Independent saved preferences.');
} finally { $database->close(); }
echo "Passed {$checks} settings checks." . PHP_EOL;
