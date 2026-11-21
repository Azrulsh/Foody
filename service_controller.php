<?php

declare(strict_types=1);

require_once __DIR__ . '/service_functions.php';

// Included before HTML by the existing weekly and monthly pages.
$serviceModule = $serviceModule ?? 'service';
if (!isset($servicePeriod) || !isset(SERVICE_SLOT_COUNTS[$servicePeriod]) || !in_array($serviceModule, ['service', 'report'], true)) {
    http_response_code(404);
    exit;
}

serviceStartSession();

$serviceMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($serviceMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$serviceRestaurant = $_GET['restaurant'] ?? $_SESSION['service_restaurant'] ?? 'jamilah';
if (!is_string($serviceRestaurant) || !in_array($serviceRestaurant, SERVICE_RESTAURANTS, true)) {
    http_response_code(400);
    exit('Invalid restaurant.');
}
$_SESSION['service_restaurant'] = $serviceRestaurant;
if (!isset($_SESSION['service_csrf'])) {
    $_SESSION['service_csrf'] = bin2hex(random_bytes(32));
}

$serviceError = '';
$serviceValues = array_fill(0, SERVICE_SLOT_COUNTS[$servicePeriod], '0');
$serviceTotal = '0.00';
$serviceDatabaseReady = false;
$serviceDatabase = null;
$serviceAmounts = null;

if ($serviceMethod === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!is_string($submittedToken) || !hash_equals($_SESSION['service_csrf'], $submittedToken)) {
        http_response_code(403);
        $serviceError = 'Your form has expired. Reload this page and try again.';
    } else {
        try {
            $serviceAmounts = serviceValidateAmounts($_POST['amounts'] ?? null, count($serviceValues));
            $serviceValues = array_map('serviceFormatInput', $serviceAmounts);
            $serviceTotal = serviceFormatMoney(array_sum($serviceAmounts));
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $serviceError = $exception->getMessage();
            // Keep scalar user input for correction; escape it only when rendering.
            foreach ($serviceValues as $index => $value) {
                $submitted = $_POST['amounts'][$index] ?? null;
                if (is_string($submitted) && strlen($submitted) <= 32) {
                    $serviceValues[$index] = $submitted;
                }
            }
        }
    }
}

try {
    $serviceDatabase = serviceDatabase();
    if ($serviceMethod === 'GET') {
        $serviceAmounts = serviceLoadAmounts($serviceDatabase, $serviceRestaurant, $servicePeriod, $serviceModule);
        $serviceValues = array_map('serviceFormatInput', $serviceAmounts);
        $serviceTotal = serviceFormatMoney(array_sum($serviceAmounts));
    } elseif ($serviceError === '' && $serviceAmounts !== null) {
        serviceSaveAmounts($serviceDatabase, $serviceRestaurant, $servicePeriod, $serviceAmounts, $serviceModule);
        $_SESSION['service_calculation'] = [
            'restaurant' => $serviceRestaurant,
            'period' => $servicePeriod,
            'total_cents' => array_sum($serviceAmounts),
            'module' => $serviceModule,
        ];
        session_write_close();
        header('Location: restaurant.php?' . http_build_query([
            'restaurant' => $serviceRestaurant,
            'module' => $serviceModule,
            'period' => $servicePeriod,
        ], '', '&', PHP_QUERY_RFC3986), true, 303);
        exit;
    }
    $serviceDatabaseReady = true;
} catch (Throwable $exception) {
    error_log('Foody ' . $serviceModule . ' module: ' . $exception->getMessage());
    if ($serviceError === '') {
        http_response_code(503);
        $serviceError = ucfirst($serviceModule) . ' data is unavailable. Please try again later. Your changes have not been saved.';
    }
} finally {
    if ($serviceDatabase instanceof mysqli) {
        $serviceDatabase->close();
    }
}

$serviceQuery = '?restaurant=' . rawurlencode($serviceRestaurant);
