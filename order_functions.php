<?php

declare(strict_types=1);

require_once __DIR__ . '/restaurant_functions.php';

const ORDER_PAGE_SIZE = 25;

function orderPositiveInteger(mixed $value, string $label): int
{
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,14}\z/D', $value)) {
        throw new InvalidArgumentException('Invalid ' . $label . '. Reload the page.');
    }
    return (int) $value;
}

/** @return array{restaurant: string, date: string, customer: string, total: int} */
function orderValidate(array $input): array
{
    $restaurant = restaurantCode($input['restaurant'] ?? null);
    $date = $input['order_date'] ?? null;
    if (!is_string($date) || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D', $date)) {
        throw new InvalidArgumentException('Enter a valid order date.');
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Enter a valid order date.');
    }
    $customer = $input['customer_name'] ?? null;
    if (!is_string($customer) || !preg_match('/\A[^\p{C}]{1,100}\z/u', trim($customer)) || preg_match('/\p{C}/u', $customer)) {
        throw new InvalidArgumentException('Enter a customer name between 1 and 100 characters.');
    }
    return [
        'restaurant' => $restaurant, 'date' => $date,
        'customer' => trim($customer), 'total' => serviceMoneyToCents($input['total'] ?? null),
    ];
}

function orderNumber(int $id): string
{
    return 'FD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function orderCreate(mysqli $database, array $input, int $userId, string $requestKey): int
{
    $values = orderValidate($input);
    if ($userId < 1 || !preg_match('/\A[a-f0-9]{64}\z/D', $requestKey)) {
        throw new InvalidArgumentException('Invalid order form. Reload the page.');
    }
    $statement = $database->prepare('INSERT INTO orders (restaurant_code, order_date, customer_name, total_cents, request_key, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    try {
        $statement->bind_param('sssisii', $values['restaurant'], $values['date'], $values['customer'], $values['total'], $requestKey, $userId, $userId);
        $statement->execute();
        return (int) $database->insert_id;
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) {
            throw new DomainException('This order form was already submitted. Reload the list before adding another order.');
        }
        throw $exception;
    } finally {
        $statement->close();
    }
}

/** Completed and cancelled orders are final; only pending orders can transition. */
function orderSetStatus(mysqli $database, int $id, int $revision, string $status, int $userId): void
{
    if ($id < 1 || $revision < 1 || $userId < 1 || !in_array($status, ['completed', 'cancelled'], true)) {
        throw new InvalidArgumentException('Choose Completed or Cancelled for a pending order.');
    }
    $statement = $database->prepare("UPDATE orders SET status = ?, updated_by = ?, revision = revision + 1 WHERE id = ? AND revision = ? AND status = 'pending'");
    try {
        $statement->bind_param('siii', $status, $userId, $id, $revision);
        $statement->execute();
        if ($statement->affected_rows !== 1) {
            throw new DomainException('This order is unavailable or has already changed. Reload the list before updating again.');
        }
    } finally {
        $statement->close();
    }
}

/** @return array{rows: list<array>, page: int, pages: int, count: int} */
function orderList(mysqli $database, string $restaurant, int $page): array
{
    if ($restaurant !== '') restaurantCode($restaurant);
    if ($page < 1) throw new InvalidArgumentException('Invalid page number.');
    $statement = $database->prepare("SELECT COUNT(*) AS total FROM orders WHERE (? = '' OR restaurant_code = ?)");
    try {
        $statement->bind_param('ss', $restaurant, $restaurant);
        $statement->execute();
        $count = (int) $statement->get_result()->fetch_assoc()['total'];
    } finally {
        $statement->close();
    }
    $pages = max(1, (int) ceil($count / ORDER_PAGE_SIZE));
    $page = min($page, $pages);
    $offset = ($page - 1) * ORDER_PAGE_SIZE;
    $limit = ORDER_PAGE_SIZE;
    $statement = $database->prepare("SELECT o.id, o.restaurant_code, r.name AS restaurant_name, o.order_date, o.customer_name, o.total_cents, o.status, o.revision FROM orders o JOIN restaurants r ON r.code = o.restaurant_code WHERE (? = '' OR o.restaurant_code = ?) ORDER BY o.order_date DESC, o.id DESC LIMIT ? OFFSET ?");
    try {
        $statement->bind_param('ssii', $restaurant, $restaurant, $limit, $offset);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally {
        $statement->close();
    }
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'count' => $count];
}
