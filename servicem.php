<?php
declare(strict_types=1);
$servicePeriod = 'monthly';
require __DIR__ . '/service_controller.php';
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="css/Background.css">
    <script src="service.js" defer></script>
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
            <!--
                <button id=""style="float:bottom;" onclick="document.location=''">Main Page</button>
            -->
        <br>
        <a href="main.php"> <button>Back</button></a>
        <p>SERVICE/REPORT LIST
        <p>
        <p1>DURATION:
        <a href="servicew.php<?= serviceEscape($serviceQuery) ?>"> <button>Weekly</button></a>
        <button type="submit">Monthly</button>
        </p1>
        <br>
        <br>
        <p2>TYPE:
        <button type="submit">Service</button>
        <a href="reportm.php<?= serviceEscape($serviceQuery) ?>"> <button>Report</button></a>
        <br>
        <hr>
        </p2>
        <?php if ($serviceError !== ''): ?>
        <p role="alert"><?= serviceEscape($serviceError) ?></p>
        <?php endif; ?>
        <form id="service-form" data-service-form method="post" action="servicem.php<?= serviceEscape($serviceQuery) ?>">
        <input type="hidden" name="csrf_token" value="<?= serviceEscape($_SESSION['service_csrf']) ?>">
        <p>5732 Services Completed
        <br>
        <table border="1" align="center">
        <p> Week 1
            <br>
            1233 Services Completed RM:
            <input type="number" name="amounts[0]" value="<?= serviceEscape($serviceValues[0]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Week 1 amount RM" form="service-form">
            <p> Week 2
            <br>
            1235 Services Completed RM:
            <input type="number" name="amounts[1]" value="<?= serviceEscape($serviceValues[1]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Week 2 amount RM" form="service-form">
            <p> Week 3
            <br>
            1227 Services Completed RM:
            <input type="number" name="amounts[2]" value="<?= serviceEscape($serviceValues[2]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Week 3 amount RM" form="service-form">
            <p> Week 4
            <br>
            1234 Services Completed RM:
            <input type="number" name="amounts[3]" value="<?= serviceEscape($serviceValues[3]) ?>" min="0" max="9999999.99" step="any" required data-service-amount aria-label="Week 4 amount RM" form="service-form">
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
