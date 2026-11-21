<?php

declare(strict_types=1);

require_once __DIR__ . '/settings_functions.php';

/** Cookie contains cosmetic choices only; it never determines account settings. */
function appearanceBrowser(): array
{
    $cookie = $_COOKIE['foody_appearance'] ?? null;
    if (is_string($cookie) && preg_match('/\A(light|dark)\.(solid|transparent)\z/D', $cookie, $matches)) {
        return ['theme' => $matches[1], 'panel_background' => $matches[2]];
    }
    return ['theme' => 'light', 'panel_background' => 'transparent'];
}

function appearanceRemember(array $settings): void
{
    $theme = $settings['theme'] ?? 'light';
    $panel = $settings['panel_background'] ?? 'transparent';
    if (!in_array($theme, ['light', 'dark'], true) || !in_array($panel, ['solid', 'transparent'], true)) {
        throw new InvalidArgumentException('Invalid appearance choices.');
    }
    setcookie('foody_appearance', $theme . '.' . $panel, [
        'expires' => time() + 31536000,
        'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Account preference wins on every protected page; cosmetic failure uses defaults. */
function appearanceForUser(array $user): array
{
    $database = null;
    try {
        $database = serviceDatabase();
        $settings = settingsLoad($database, (int) $user['id']);
        appearanceRemember($settings);
        return ['theme' => $settings['theme'], 'panel_background' => $settings['panel_background']];
    } catch (Throwable $exception) {
        error_log('Foody appearance: ' . $exception->getMessage());
        return ['theme' => 'light', 'panel_background' => 'transparent'];
    } finally {
        if ($database instanceof mysqli) $database->close();
    }
}

function appearanceAttributes(array $appearance): string
{
    $theme = in_array($appearance['theme'] ?? null, ['light', 'dark'], true) ? $appearance['theme'] : 'light';
    $panel = in_array($appearance['panel_background'] ?? null, ['solid', 'transparent'], true) ? $appearance['panel_background'] : 'transparent';
    return 'data-theme="' . $theme . '" data-panel="' . $panel . '"';
}
