<?php
declare(strict_types=1);
$serviceModule = 'report';
$servicePeriod = 'weekly';
require __DIR__ . '/service_controller.php';
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceForUser($currentUser);
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>

<head>
    <link rel="stylesheet" href="Background.css">
    <script src="service.js" defer></script>
    <link rel="stylesheet" href="reportm.css">

    <link rel="stylesheet" href="theme.css">
</head>
<style>
    #backgroundmain {
        background-color: rgba(255, 255, 255, 0.5);

    }

</style>

<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="3" style="width: 160px;height: 700px;">
                <?php include 'sidebar.php' ?>
            </td>
            <td  style="width: 1000px;height: 0px;text-align:center">
            <header> WELCOME, <span class="user-name"><?= serviceEscape($currentUser['name']) ?></span></header>
                </td>
        </tr>
        <tr>

            <td  id="backgroundmain">
            <!--
                <button id=""style="float:bottom;" onclick="document.location=''">Main Page</button>
            -->
        <br>
        <a href="main.php"> <button>Back</button></a>
        <p>SERVICE/REPORT LIST
        <p>
        <p1>DURATION:
        <button type="submit">Weekly</button>
        <a href="reportm.php<?= serviceEscape($serviceQuery) ?>"> <button>Monthly</button></a>
        </p1>
        <br>
        <br>
        <p2>TYPE:
        <a href="servicew.php<?= serviceEscape($serviceQuery) ?>"> <button>Service</button></a>
        <button type="submit">Report</button>
        <br>
        </p2>
        <hr>
        <?php if ($serviceError !== ''): ?>
        <p role="alert"><?= serviceEscape($serviceError) ?></p>
        <?php endif; ?>
        <form id="service-form" data-service-form method="post" action="reportw.php<?= serviceEscape($serviceQuery) ?>">
        <input type="hidden" name="csrf_token" value="<?= serviceEscape($_SESSION['service_csrf']) ?>" form="service-form">
        <p>732 Total Reports
        <br>
        <table border="1" align="center">
        <p> Day 1
            <br>
            145 Total Reports -RM:
            <input type="number" name="amounts[0]" value="<?= serviceEscape($serviceValues[0]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 1 report amount RM" form="service-form">
            <p> Day 2
            <br>
            129 Total Reports -RM:
            <input type="number" name="amounts[1]" value="<?= serviceEscape($serviceValues[1]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 2 report amount RM" form="service-form">
            <p> Day 3
            <br>
            121 Total Reports -RM:
            <input type="number" name="amounts[2]" value="<?= serviceEscape($serviceValues[2]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 3 report amount RM" form="service-form">
            <p> Day 4
            <br>
            134 Total Reports -RM:
            <input type="number" name="amounts[3]" value="<?= serviceEscape($serviceValues[3]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 4 report amount RM" form="service-form">
            <p> Day 5
            <br>
            154 Total Reports -RM:
            <input type="number" name="amounts[4]" value="<?= serviceEscape($serviceValues[4]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 5 report amount RM" form="service-form">
            <p> Day 6
            <br>
            123 Total Reports -RM:
            <input type="number" name="amounts[5]" value="<?= serviceEscape($serviceValues[5]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 6 report amount RM" form="service-form">
            <p> Day 7
            <br>
            124 Total Reports -RM:
            <input type="number" name="amounts[6]" value="<?= serviceEscape($serviceValues[6]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Day 7 report amount RM" form="service-form">
            <br>
            <br>
             Total RM:
             <input type="number" value="<?= serviceEscape($serviceTotal) ?>" step="0.01" readonly data-service-total aria-label="Total RM" form="service-form">
            <hr>
        </table>
        <input type="reset" value="Delete" form="service-form">
            <button type="submit" form="service-form" <?= $serviceDatabaseReady ? '' : 'disabled' ?>>Calculation</button>
        </br>
        </p>
        </td>
       </form>
            </td>
        </tr>
    </table>

</body>

</html>