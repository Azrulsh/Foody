<?php
declare(strict_types=1);
define('FOODY_ORDER_PAGE', true);
require __DIR__ . '/order_controller.php';
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceForUser($currentUser);
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order List - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
    <script src="order.js" defer></script>
    <link rel="stylesheet" href="theme.css">
</head>
<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="2" style="width:160px;height:700px"><?php require __DIR__ . '/sidebar.php'; ?></td>
            <td style="width:1000px;text-align:center"><header>ORDER LIST</header></td>
        </tr>
        <tr><td class="profile-content">
            <?php if ($error !== ''): ?><p role="alert"><?= serviceEscape($error) ?></p><?php endif; ?>
            <?php if ($saved !== ''): ?><p role="status"><?= serviceEscape($saved) ?></p><?php endif; ?>
            <?php if ($ready): ?>
            <h2>Add Order</h2>
            <form action="<?= serviceEscape($orderActionUrl) ?>" method="post" class="profile-form" id="order-create">
                <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="request_key" value="<?= serviceEscape($orderRequestKey) ?>">
                <label for="order-restaurant">Restaurant</label>
                <select id="order-restaurant" name="restaurant" required>
                    <?php foreach ($restaurants as $code => $restaurant): ?>
                    <option value="<?= serviceEscape($code) ?>" <?= $values['restaurant'] === $code ? 'selected' : '' ?>><?= serviceEscape($restaurant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="order-date">Order date</label>
                <input id="order-date" type="date" name="order_date" min="1000-01-01" max="9999-12-31" value="<?= serviceEscape($values['order_date']) ?>" required>
                <label for="order-customer">Customer name</label>
                <input id="order-customer" name="customer_name" maxlength="100" value="<?= serviceEscape($values['customer_name']) ?>" required>
                <label for="order-total">Total RM</label>
                <input id="order-total" name="total" type="number" min="0" max="9999999.99" step="any" value="<?= serviceEscape($values['total']) ?>" required>
                <button type="submit">Add</button>
            </form>
            <hr>
            <h2>Orders</h2>
            <form action="orders.php" method="get">
                <label for="restaurant-filter">Restaurant</label>
                <select id="restaurant-filter" name="restaurant">
                    <option value="">All restaurants</option>
                    <?php foreach ($restaurants as $code => $restaurant): ?>
                    <option value="<?= serviceEscape($code) ?>" <?= $filter === $code ? 'selected' : '' ?>><?= serviceEscape($restaurant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
            </form>
            <p><?= serviceEscape((string) $listing['count']) ?> orders</p>
            <div style="overflow-x:auto">
                <table border="1" cellpadding="8" class="order-table">
                    <thead><tr><th scope="col">Order number</th><th scope="col">Date</th><th scope="col">Restaurant</th><th scope="col">Customer</th><th scope="col">Total RM</th><th scope="col">Status</th><th scope="col">Update status</th></tr></thead>
                    <tbody>
                    <?php if ($listing['rows'] === []): ?><tr><td colspan="7">No orders found.</td></tr><?php endif; ?>
                    <?php foreach ($listing['rows'] as $order): ?>
                    <tr>
                        <td><?= serviceEscape(orderNumber((int) $order['id'])) ?></td>
                        <td><?= serviceEscape(settingsFormatDate($order['order_date'], $preferences['date_format'])) ?></td>
                        <td><?= serviceEscape($order['restaurant_name']) ?></td>
                        <td><?= serviceEscape($order['customer_name']) ?></td>
                        <td><?= serviceEscape(serviceFormatMoney((int) $order['total_cents'])) ?></td>
                        <td><?= serviceEscape(ucfirst($order['status'])) ?></td>
                        <td>
                            <?php if ($order['status'] === 'pending'): ?>
                            <form action="<?= serviceEscape($orderActionUrl) ?>" method="post">
                                <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= serviceEscape((string) $order['id']) ?>">
                                <input type="hidden" name="revision" value="<?= serviceEscape((string) $order['revision']) ?>">
                                <select name="status" aria-label="New status for <?= serviceEscape(orderNumber((int) $order['id'])) ?>" required>
                                    <option value="">Choose status</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="submit">Update</button>
                            </form>
                            <?php else: ?>Final<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Order pages">
                <?php if ($listing['page'] > 1): ?><a href="orders.php?<?= serviceEscape(http_build_query(['restaurant' => $filter, 'page' => $listing['page'] - 1])) ?>">Previous</a><?php endif; ?>
                Page <?= serviceEscape((string) $listing['page']) ?> of <?= serviceEscape((string) $listing['pages']) ?>
                <?php if ($listing['page'] < $listing['pages']): ?><a href="orders.php?<?= serviceEscape(http_build_query(['restaurant' => $filter, 'page' => $listing['page'] + 1])) ?>">Next</a><?php endif; ?>
            </nav>
            <?php endif; ?>
        </td></tr>
    </table>
</body>
</html>
