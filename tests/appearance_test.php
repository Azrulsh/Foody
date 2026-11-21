<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/appearance_functions.php';
$checks = 0;
function appearanceCheck(bool $condition): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('Appearance check failed.');
}
foreach (['light', 'dark'] as $theme) {
    foreach (['solid', 'transparent'] as $panel) {
        $_COOKIE['foody_appearance'] = $theme . '.' . $panel;
        appearanceCheck(appearanceBrowser() === ['theme' => $theme, 'panel_background' => $panel]);
        appearanceCheck(appearanceAttributes(appearanceBrowser()) === 'data-theme="' . $theme . '" data-panel="' . $panel . '"');
    }
}
foreach ([null, [], 'dark.bad', 'dark.solid" onclick="bad', 'light.solid.extra'] as $bad) {
    $_COOKIE['foody_appearance'] = $bad;
    appearanceCheck(appearanceBrowser() === ['theme' => 'light', 'panel_background' => 'transparent']);
}
appearanceCheck(appearanceAttributes(['theme' => '<script>', 'panel_background' => []]) === 'data-theme="light" data-panel="transparent"');
echo "Passed {$checks} appearance checks." . PHP_EOL;
