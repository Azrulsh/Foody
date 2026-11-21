<?php

declare(strict_types=1);

const SERVICE_RESTAURANTS = ['jamilah', 'greenview', 'lepak'];
const SERVICE_SLOT_COUNTS = ['weekly' => 7, 'monthly' => 4];
const SERVICE_MAX_CENTS = 999999999;

function serviceStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('FOODYSESSID');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/',
    ]);
    if (!session_start()) {
        error_log('Foody: session storage is unavailable.');
        http_response_code(503);
        exit('Your session could not be started. Please try again later.');
    }
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
}

function serviceEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Convert validated decimal money to integer cents without floating point rounding. */
function serviceMoneyToCents(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?\z/D', $value, $matches)) {
        throw new InvalidArgumentException('Enter an amount from 0 to 9999999.99 with up to two decimal places.');
    }

    return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
}

function serviceFormatMoney(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function serviceFormatInput(int $cents): string
{
    return rtrim(rtrim(serviceFormatMoney($cents), '0'), '.');
}

/** @return list<int> */
function serviceValidateAmounts(mixed $amounts, int $slotCount): array
{
    if (!is_array($amounts) || array_keys($amounts) !== range(0, $slotCount - 1)) {
        throw new InvalidArgumentException('Complete every amount before calculating.');
    }

    return array_map('serviceMoneyToCents', $amounts);
}

function serviceDatabase(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $database = mysqli_init();
    $database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $port = getenv('FOODY_DB_PORT') ?: '3306';
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('Invalid database port configuration.');
    }
    $database->real_connect(
        getenv('FOODY_DB_HOST') ?: 'localhost',
        getenv('FOODY_DB_USER') ?: 'root',
        getenv('FOODY_DB_PASSWORD') ?: '',
        getenv('FOODY_DB_NAME') ?: 'foody',
        (int) $port
    );
    $database->set_charset('utf8mb4');

    return $database;
}

/** @return list<int> */
function serviceLoadAmounts(mysqli $database, string $restaurant, string $period, string $module = 'service'): array
{
    $table = serviceAmountTable($module);
    $amounts = array_fill(0, SERVICE_SLOT_COUNTS[$period], 0);
    $statement = $database->prepare("SELECT slot_number, amount_cents FROM {$table} WHERE restaurant = ? AND period = ? ORDER BY slot_number");
    try {
        $statement->bind_param('ss', $restaurant, $period);
        $statement->execute();
        $statement->bind_result($slot, $cents);
        while ($statement->fetch()) {
            if ($slot >= 1 && $slot <= count($amounts) && $cents >= 0 && $cents <= SERVICE_MAX_CENTS) {
                $amounts[$slot - 1] = (int) $cents;
            } else {
                throw new RuntimeException('Invalid stored service amount.');
            }
        }
    } finally {
        $statement->close();
    }

    return $amounts;
}

/** @param list<int> $amounts */
function serviceSaveAmounts(mysqli $database, string $restaurant, string $period, array $amounts, string $module = 'service'): void
{
    $table = serviceAmountTable($module);
    $database->begin_transaction();
    try {
        $statement = $database->prepare("INSERT INTO {$table} (restaurant, period, slot_number, amount_cents) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents)");
        try {
            $statement->bind_param('ssii', $restaurant, $period, $slot, $cents);
            foreach ($amounts as $index => $cents) {
                $slot = $index + 1;
                $statement->execute();
            }
        } finally {
            $statement->close();
        }
        $database->commit();
    } catch (Throwable $exception) {
        $database->rollback();
        throw $exception;
    }
}

/** SQL identifiers must come exclusively from this fixed allowlist. */
function serviceAmountTable(string $module): string
{
    return match ($module) {
        'service' => 'service_amounts',
        'report' => 'report_amounts',
        default => throw new InvalidArgumentException('Invalid amount module.'),
    };
}
