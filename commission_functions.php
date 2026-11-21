<?php

declare(strict_types=1);

require_once __DIR__ . '/service_functions.php';

/** @return array{restaurant: string, module: string, period: string} */
function commissionContext(array $input): array
{
    $context = [
        'restaurant' => $input['restaurant'] ?? 'jamilah',
        'module' => $input['module'] ?? 'service',
        'period' => $input['period'] ?? 'weekly',
    ];
    foreach ($context as $value) {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid commission selection.');
        }
    }
    if (!in_array($context['restaurant'], SERVICE_RESTAURANTS, true)
        || !in_array($context['module'], ['service', 'report'], true)
        || !isset(SERVICE_SLOT_COUNTS[$context['period']])) {
        throw new InvalidArgumentException('Invalid commission selection.');
    }

    return $context;
}

function commissionUrl(array $context): string
{
    return 'restaurant.php?' . http_build_query($context, '', '&', PHP_QUERY_RFC3986);
}

function commissionFormatMoney(int $cents): string
{
    return ($cents < 0 ? '-' : '') . serviceFormatMoney(abs($cents));
}

/** @return array{gross: int, penalties: int, delivery: int, nett: int} */
function commissionValidateAmounts(array $input): array
{
    $gross = serviceMoneyToCents($input['commissions'] ?? null);
    $penalties = serviceMoneyToCents($input['penalties'] ?? null);
    $delivery = serviceMoneyToCents($input['services'] ?? null);

    // Never trust a submitted NETT, including values altered outside the browser.
    return ['gross' => $gross, 'penalties' => $penalties, 'delivery' => $delivery, 'nett' => $gross - $penalties - $delivery];
}

/** @return array{gross: int, penalties: int, delivery: int, nett: int, revision: int}|null */
function commissionLoad(mysqli $database, array $context): ?array
{
    $statement = $database->prepare('SELECT gross_cents, penalty_cents, delivery_cents, revision FROM commission_records WHERE restaurant = ? AND source_module = ? AND period = ?');
    try {
        $statement->bind_param('sss', $context['restaurant'], $context['module'], $context['period']);
        $statement->execute();
        $statement->bind_result($gross, $penalties, $delivery, $revision);
        if (!$statement->fetch()) {
            return null;
        }

        return ['gross' => (int) $gross, 'penalties' => (int) $penalties, 'delivery' => (int) $delivery, 'nett' => (int) $gross - (int) $penalties - (int) $delivery, 'revision' => (int) $revision];
    } finally {
        $statement->close();
    }
}

/** Save exactly one scoped record; reject duplicate adds and stale updates. */
function commissionSave(mysqli $database, array $context, array $input, string $action, int $revision): void
{
    $context = commissionContext($context);
    $values = commissionValidateAmounts($input);
    if (!in_array($action, ['add', 'update'], true) || $revision < 0) {
        throw new InvalidArgumentException('Invalid commission action.');
    }
    if ($action === 'update' && $revision === 0) {
        throw new DomainException('No saved commission exists. Use Add first.');
    }

    if ($action === 'add') {
        $statement = $database->prepare('INSERT INTO commission_records (restaurant, source_module, period, gross_cents, penalty_cents, delivery_cents) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bind_param('sssiii', $context['restaurant'], $context['module'], $context['period'], $values['gross'], $values['penalties'], $values['delivery']);
    } else {
        $statement = $database->prepare('UPDATE commission_records SET gross_cents = ?, penalty_cents = ?, delivery_cents = ?, revision = revision + 1 WHERE restaurant = ? AND source_module = ? AND period = ? AND revision = ?');
        $statement->bind_param('iiisssi', $values['gross'], $values['penalties'], $values['delivery'], $context['restaurant'], $context['module'], $context['period'], $revision);
    }
    try {
        $statement->execute();
        if ($action === 'update' && $statement->affected_rows !== 1) {
            throw new DomainException('This commission has changed. Reload the page before updating again.');
        }
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) {
            throw new DomainException('A commission already exists for this selection. Reload the page and use Update.', 0, $exception);
        }
        throw $exception;
    } finally {
        $statement->close();
    }
}
