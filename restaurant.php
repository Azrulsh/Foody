<?php
declare(strict_types=1);
require_once __DIR__ . '/service_functions.php';
serviceStartSession();
$serviceContext = $_SESSION['service_calculation'] ?? [];
$serviceBackPage = ($serviceContext['period'] ?? '') === 'monthly' ? 'servicem.php' : 'servicew.php';
$serviceBackRestaurant = $serviceContext['restaurant'] ?? $_SESSION['service_restaurant'] ?? 'jamilah';
if (!is_string($serviceBackRestaurant) || !in_array($serviceBackRestaurant, SERVICE_RESTAURANTS, true)) {
    $serviceBackRestaurant = 'jamilah';
}
$serviceBackUrl = $serviceBackPage . '?restaurant=' . rawurlencode($serviceBackRestaurant);
session_write_close();

// The commission module remains independent; service totals are not commission rates.
$commissions = $penalties = $services = $totals = '0';
$commissionReadError = '';
$commissionDatabase = null;
try {
    $commissionDatabase = serviceDatabase();
    $commissionRow = $commissionDatabase->query('SELECT commissions, penalties, services, totals FROM commission ORDER BY ID LIMIT 1')->fetch_assoc();
    if ($commissionRow !== null) {
        $commissions = (string) $commissionRow['commissions'];
        $penalties = (string) $commissionRow['penalties'];
        $services = (string) $commissionRow['services'];
        $totals = (string) $commissionRow['totals'];
    }
} catch (Throwable $exception) {
    error_log('Foody commission read: ' . $exception->getMessage());
    http_response_code(503);
    $commissionReadError = 'Commission data is unavailable. Please try again later.';
} finally {
    if ($commissionDatabase instanceof mysqli) {
        $commissionDatabase->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="css/Background.css">
    <link href="Calculator with Js" rel="calculate.js">
    <link rel="stylesheet" href="reportm.css">
     
</head>
<style>
    #backgroundmain {
        background-color: white;
        opacity: 0.5;
    }

</style>

<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="3" style="width: 160px;height: 700px;">
                <?php include 'sidebar.php' ?>
            </td>
            <td  style="width: 1000px;height: 0px;text-align:center">
            <header> WELCOME, ACCOUNTANT</header>
                </td>   
        </tr>
        <tr>
    
            <td  id="backgroundmain">
            <a href="<?= serviceEscape($serviceBackUrl) ?>"> <button>Back</button></a>
            <?php if ($commissionReadError !== ''): ?>
            <p role="alert"><?= serviceEscape($commissionReadError) ?></p>
            <?php endif; ?>
                    <form action="kemas.php" method="post"> 
        <br>
        <p1>TOTAL COMMISSION RM:
        <input type="number" id="fname" name="commissions" value="<?= serviceEscape($commissions) ?>"><br>
</p1>
        <br>
        <p2>PENALTIES RM:
        <input type="number" id="fname" name="penalties" value="<?= serviceEscape($penalties) ?>"><br>
</p2>
        <br>
        <p3>DELIVERY PER SERVICES:
        <input type="number" id="fname" name="services" value="<?= serviceEscape($services) ?>"><br>
</p3>
        <br>
        <p4>NETT COMMISSION RM:
        <input type="text" id="fname" name="totals" value="<?= serviceEscape($totals) ?>"><br>
        <br>
        <input type="reset" value="Delete">
        <input type="submit" value="Add">
        <button type="update">Update</button>
        </br>
        </p4>
        </td>
       </form>
            </td>
        </tr>
    </table>

</body>

</html>
