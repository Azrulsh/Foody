<?php

declare(strict_types=1);

require_once __DIR__ . '/service_functions.php';

function restaurantCode(mixed $value): string
{
    if (!is_string($value) || !in_array($value, SERVICE_RESTAURANTS, true)) {
        throw new InvalidArgumentException('Invalid restaurant selection.');
    }
    return $value;
}

function restaurantRevision(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,14}\z/D', $value)) {
        throw new InvalidArgumentException('Invalid restaurant version. Reload the page.');
    }
    return (int) $value;
}

/** @return array{name: string, address: string, phone: string} */
function restaurantValidate(array $input): array
{
    $name = $input['name'] ?? null;
    $address = $input['address'] ?? null;
    $phone = $input['phone'] ?? null;
    if (!is_string($name) || !preg_match('/\A[^\p{C}]{1,100}\z/u', trim($name)) || preg_match('/\p{C}/u', $name)) {
        throw new InvalidArgumentException('Enter a restaurant name between 1 and 100 characters.');
    }
    if (!is_string($address) || !mb_check_encoding($address, 'UTF-8')) {
        throw new InvalidArgumentException('Enter a valid restaurant address.');
    }
    // Line breaks are valid in addresses; invisible controls are not.
    if (preg_match('/\p{C}/u', str_replace(["\r", "\n"], '', $address))) {
        throw new InvalidArgumentException('The address contains invalid characters.');
    }
    $address = trim(str_replace(["\r\n", "\r"], "\n", $address));
    if (mb_strlen($address, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Enter an address of up to 500 characters.');
    }
    if (!is_string($phone) || preg_match('/[\x00-\x1F\x7F]/', $phone)) {
        throw new InvalidArgumentException('Enter a valid phone number.');
    }
    $phone = trim($phone);
    if ($phone !== '' && (!preg_match('/\A\+?[0-9][0-9 ()-]{0,28}\z/D', $phone)
        || !preg_match('/\A[0-9]{6,15}\z/D', preg_replace('/[^0-9]/', '', $phone)))) {
        throw new InvalidArgumentException('Enter a phone number with 6 to 15 digits; spaces, brackets, hyphens and a leading + are allowed.');
    }
    return ['name' => trim($name), 'address' => $address, 'phone' => $phone];
}

/** @return array<string, array{code: string, name: string, address: string, phone: string, revision: int}> */
function restaurantLoadAll(mysqli $database): array
{
    $rows = [];
    $result = $database->query('SELECT code, name, address, phone, revision FROM restaurants');
    try {
        while ($row = $result->fetch_assoc()) {
            $code = restaurantCode($row['code']);
            $row['revision'] = (int) $row['revision'];
            $rows[$code] = $row;
        }
    } finally {
        $result->free();
    }
    if (count($rows) !== count(SERVICE_RESTAURANTS)) {
        throw new RuntimeException('Restaurant setup is incomplete.');
    }
    // Return the original display order, independently of database row ordering.
    return array_replace(array_fill_keys(SERVICE_RESTAURANTS, []), $rows);
}

function restaurantUpdate(mysqli $database, string $code, array $input, int $revision): void
{
    $code = restaurantCode($code);
    $values = restaurantValidate($input);
    if ($revision < 1) {
        throw new InvalidArgumentException('Invalid restaurant version. Reload the page.');
    }
    $statement = $database->prepare('UPDATE restaurants SET name = ?, address = ?, phone = ?, revision = revision + 1 WHERE code = ? AND revision = ?');
    try {
        $statement->bind_param('ssssi', $values['name'], $values['address'], $values['phone'], $code, $revision);
        $statement->execute();
        if ($statement->affected_rows !== 1) {
            throw new DomainException('This restaurant has changed. Reload the page before saving again.');
        }
    } finally {
        $statement->close();
    }
}
