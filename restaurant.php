<?php
declare(strict_types=1);
define('FOODY_COMMISSION_PAGE', true);
require __DIR__ . '/commission_controller.php';
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceForUser($currentUser);
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>

<head>
    <link rel="stylesheet" href="Background.css">
    <script src="commission.js" defer></script>
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
            <td class="sidebar-cell" rowspan="3" style="width:160px;">
                <?php include 'sidebar.php' ?>
            </td>
            <td  style="width: 1000px;height: 0px;text-align:center">
            <header> WELCOME, <span class="user-name"><?= serviceEscape($currentUser['name']) ?></span></header>
                </td>
        </tr>
        <tr>

            <td  id="backgroundmain">
            <a href="<?= serviceEscape($serviceBackUrl) ?>"> <button>Back</button></a>
            <?php if ($commissionReadError !== ''): ?>
            <p role="alert"><?= serviceEscape($commissionReadError) ?></p>
            <?php endif; ?>
                    <form id="commission-form" data-commission-form action="insert.php" method="post">
        <input type="hidden" name="commission_token" value="<?= serviceEscape($commissionFormToken) ?>" form="commission-form">
        <br>
        <p1>TOTAL COMMISSION RM:
        <input type="number" id="commissions" name="commissions" min="0" max="9999999.99" step="any" required form="commission-form" value="<?= serviceEscape($commissions) ?>"><br>
</p1>
        <br>
        <p2>PENALTIES RM:
        <input type="number" id="penalties" name="penalties" min="0" max="9999999.99" step="any" required form="commission-form" value="<?= serviceEscape($penalties) ?>"><br>
</p2>
        <br>
        <p3>DELIVERY PER SERVICES:
        <input type="number" id="services" name="services" min="0" max="9999999.99" step="any" required form="commission-form" value="<?= serviceEscape($services) ?>"><br>
</p3>
        <br>
        <p4>NETT COMMISSION RM:
        <input type="text" id="totals" name="totals" readonly form="commission-form" value="<?= serviceEscape($totals) ?>"><br>
        <br>
        <input type="reset" value="Delete" form="commission-form">
        <input type="submit" value="Add" form="commission-form" formaction="insert.php" <?= $commissionReady ? '' : 'disabled' ?>>
        <button type="submit" form="commission-form" formaction="kemas.php" <?= $commissionReady ? '' : 'disabled' ?>>Update</button>
        </br>
        </p4>
        </td>
       </form>
            </td>
        </tr>
    </table>

</body>

</html>
