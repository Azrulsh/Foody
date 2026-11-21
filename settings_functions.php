<?php

declare(strict_types=1);

require_once __DIR__ . '/service_functions.php';

/** @return array{default_restaurant: string, default_duration: string, orders_per_page: int, date_format: string, theme: string, panel_background: string, revision: int} */
function settingsDefaults(): array
{
    return ['default_restaurant' => '', 'default_duration' => 'weekly', 'orders_per_page' => 25, 'date_format' => 'Y-m-d', 'theme' => 'light', 'panel_background' => 'transparent', 'revision' => 0];
}

/** Only predefined choices can enter the database or affect rendering. */
function settingsValidate(array $input): array
{
    $restaurant = $input['default_restaurant'] ?? null;
    $duration = $input['default_duration'] ?? null;
    $pageSize = $input['orders_per_page'] ?? null;
    $dateFormat = $input['date_format'] ?? null;
    $theme = $input['theme'] ?? null;
    $panel = $input['panel_background'] ?? null;
    if (!is_string($restaurant) || !in_array($restaurant, ['', ...SERVICE_RESTAURANTS], true)
        || !is_string($duration) || !in_array($duration, ['weekly', 'monthly'], true)
        || !is_string($pageSize) || !in_array($pageSize, ['10', '25', '50'], true)
        || !is_string($dateFormat) || !in_array($dateFormat, ['d/m/Y', 'Y-m-d'], true)
        || !is_string($theme) || !in_array($theme, ['light', 'dark'], true)
        || !is_string($panel) || !in_array($panel, ['solid', 'transparent'], true)) {
        throw new InvalidArgumentException('Choose a valid option for each setting.');
    }
    return ['default_restaurant' => $restaurant, 'default_duration' => $duration, 'orders_per_page' => (int) $pageSize, 'date_format' => $dateFormat, 'theme' => $theme, 'panel_background' => $panel];
}

function settingsRevision(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/D', $value)) {
        throw new InvalidArgumentException('Invalid settings version. Reload the page.');
    }
    return (int) $value;
}

function settingsLoad(mysqli $database, int $userId): array
{
    $statement = $database->prepare('SELECT default_restaurant, default_duration, orders_per_page, date_format, theme, panel_background, revision FROM user_settings WHERE user_id = ?');
    try {
        $statement->bind_param('i', $userId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        if ($row === null) return settingsDefaults();
        $input = $row;
        $input['orders_per_page'] = (string) $row['orders_per_page'];
        $settings = settingsValidate($input);
        $settings['revision'] = settingsRevision((string) $row['revision']);
        return $settings;
    } finally {
        $statement->close();
    }
}

/** User ID is supplied by the authenticated session, never by the form. */
function settingsSave(mysqli $database, int $userId, array $input, int $revision): void
{
    $settings = settingsValidate($input);
    if ($userId < 1 || $revision < 0) {
        throw new InvalidArgumentException('Invalid settings request.');
    }
    if ($revision === 0) {
        $statement = $database->prepare('INSERT INTO user_settings (user_id, default_restaurant, default_duration, orders_per_page, date_format, theme, panel_background) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->bind_param('ississs', $userId, $settings['default_restaurant'], $settings['default_duration'], $settings['orders_per_page'], $settings['date_format'], $settings['theme'], $settings['panel_background']);
    } else {
        $statement = $database->prepare('UPDATE user_settings SET default_restaurant = ?, default_duration = ?, orders_per_page = ?, date_format = ?, theme = ?, panel_background = ?, revision = revision + 1 WHERE user_id = ? AND revision = ?');
        $statement->bind_param('ssisssii', $settings['default_restaurant'], $settings['default_duration'], $settings['orders_per_page'], $settings['date_format'], $settings['theme'], $settings['panel_background'], $userId, $revision);
    }
    try {
        $statement->execute();
        if ($revision !== 0 && $statement->affected_rows !== 1) {
            throw new DomainException('Your settings have changed. Reload the page before saving again.');
        }
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) {
            throw new DomainException('Your settings have changed. Reload the page before saving again.');
        }
        throw $exception;
    } finally {
        $statement->close();
    }
}

function settingsFormatDate(string $isoDate, string $format): string
{
    if (!in_array($format, ['d/m/Y', 'Y-m-d'], true)) {
        throw new InvalidArgumentException('Invalid date display format.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
    if ($date === false || $date->format('Y-m-d') !== $isoDate) {
        throw new RuntimeException('Invalid stored order date.');
    }
    return $date->format($format);
}
