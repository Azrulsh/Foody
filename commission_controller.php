<?php

declare(strict_types=1);

require_once __DIR__ . '/commission_functions.php';

if (!defined('FOODY_COMMISSION_PAGE')) {
    http_response_code(404);
    exit;
}

serviceStartSession();
require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
$commissionMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$commissionExpectedMethod = isset($commissionAction) ? 'POST' : 'GET';
if ($commissionMethod !== $commissionExpectedMethod) {
    header('Allow: ' . $commissionExpectedMethod);
    http_response_code(405);
    exit('Method not allowed.');
}

try {
    $commissionSelection = commissionContext($_GET + ($_SESSION['service_calculation'] ?? []) + ['restaurant' => $_SESSION['service_restaurant'] ?? 'jamilah']);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    exit('Invalid commission selection.');
}

$commissionReadError = '';
$commissionReady = false;
$commissionRevision = 0;
$commissionValues = ['commissions' => '0', 'penalties' => '0', 'services' => '0'];
$totals = '0.00';
$commissionDatabase = null;
$commissionSubmittedValues = null;
$commissionSubmittedForm = null;

// Each form remembers its own context, so another tab cannot change its target.
$_SESSION['commission_forms'] ??= [];
foreach ($_SESSION['commission_forms'] as $token => $form) {
    if ($form['expires'] < time()) {
        unset($_SESSION['commission_forms'][$token]);
    }
}

if ($commissionMethod === 'POST') {
    $commissionPostedToken = $_POST['commission_token'] ?? null;
    if (!is_string($commissionPostedToken) || !isset($_SESSION['commission_forms'][$commissionPostedToken])) {
        http_response_code(403);
        $commissionReadError = 'Your form has expired. Reload the commission page and try again.';
    } else {
        $commissionSubmittedForm = $_SESSION['commission_forms'][$commissionPostedToken];
        $commissionSelection = $commissionSubmittedForm['context'];
        try {
            $commissionSubmittedValues = commissionValidateAmounts($_POST);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $commissionReadError = $exception->getMessage();
        }
        foreach ($commissionValues as $name => $value) {
            $submitted = $_POST[$name] ?? null;
            if (is_string($submitted) && strlen($submitted) <= 32) {
                $commissionValues[$name] = $submitted;
            }
        }
        if ($commissionSubmittedValues !== null) {
            $totals = commissionFormatMoney($commissionSubmittedValues['nett']);
        }
    }
}

try {
    $commissionDatabase = serviceDatabase();
    $commissionRow = commissionLoad($commissionDatabase, $commissionSelection);
    $commissionRevision = $commissionRow['revision'] ?? 0;
    if ($commissionMethod === 'GET' && $commissionRow !== null) {
        $commissionValues = [
            'commissions' => serviceFormatInput($commissionRow['gross']),
            'penalties' => serviceFormatInput($commissionRow['penalties']),
            'services' => serviceFormatInput($commissionRow['delivery']),
        ];
        $totals = commissionFormatMoney($commissionRow['nett']);
    }
    if ($commissionMethod === 'POST' && $commissionReadError === '' && $commissionSubmittedForm !== null) {
        commissionSave($commissionDatabase, $commissionSelection, $_POST, $commissionAction, $commissionSubmittedForm['revision']);
        unset($_SESSION['commission_forms'][$commissionPostedToken]);
        session_write_close();
        header('Location: ' . commissionUrl($commissionSelection), true, 303);
        exit;
    }
    $commissionReady = true;
} catch (DomainException $exception) {
    http_response_code(409);
    $commissionReadError = $exception->getMessage();
    $commissionReady = true;
} catch (Throwable $exception) {
    error_log('Foody commission module: ' . $exception->getMessage());
    if ($commissionReadError === '') {
        http_response_code(503);
        $commissionReadError = 'Commission data is unavailable. Your changes have not been saved. Please try again later.';
    }
} finally {
    if ($commissionDatabase instanceof mysqli) {
        $commissionDatabase->close();
    }
}

// Bound session storage while allowing multiple open tabs and retries.
while (count($_SESSION['commission_forms']) >= 30) {
    array_shift($_SESSION['commission_forms']);
}
$commissionFormToken = bin2hex(random_bytes(32));
// A stale form must remain stale after an error, until the user reloads.
$commissionFormRevision = $commissionSubmittedForm['revision'] ?? $commissionRevision;
$_SESSION['commission_forms'][$commissionFormToken] = [
    'context' => $commissionSelection,
    'revision' => $commissionFormRevision,
    'expires' => time() + 3600,
];
$serviceBackUrl = ($commissionSelection['module'] === 'report' ? 'report' : 'service')
    . ($commissionSelection['period'] === 'monthly' ? 'm.php' : 'w.php')
    . '?restaurant=' . rawurlencode($commissionSelection['restaurant']);
$commissions = $commissionValues['commissions'];
$penalties = $commissionValues['penalties'];
$services = $commissionValues['services'];
session_write_close();
