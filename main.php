<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
require_once __DIR__ . '/restaurant_functions.php';
require_once __DIR__ . '/settings_functions.php';
$restaurantError = '';
$restaurants = [];
$restaurantDatabase = null;
try {
    $restaurantDatabase = serviceDatabase();
    $preferences = settingsLoad($restaurantDatabase, (int) $currentUser['id']);
    $servicePage = $preferences['default_duration'] === 'monthly' ? 'servicem.php' : 'servicew.php';
    $restaurants = restaurantLoadAll($restaurantDatabase);
} catch (Throwable $exception) {
    error_log('Foody main restaurants: ' . $exception->getMessage());
    http_response_code(503);
    $restaurantError = 'Restaurant data is unavailable. Please try again later.';
} finally {
    if ($restaurantDatabase instanceof mysqli) {
        $restaurantDatabase->close();
    }
}
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceForUser($currentUser);
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>

<head>
    <link rel="stylesheet" href="Background.css">
    <link href="Calculator with Js" rel="calculate.js">
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
            <header>MAIN PAGE</header>
                </td>
        </tr>
        <tr>

            <td  id="backgroundmain">
            <!--
                <button id=""style="float:bottom;" onclick="document.location=''">Main Page</button>
            -->

        <br>
        <p>RECEIVED SERVICE/REPORT LIST
        <p>
        <?php if ($restaurantError !== ''): ?>
        <p role="alert"><?= serviceEscape($restaurantError) ?></p>
        <?php else: ?>
        <p1>
        <br>
        <br>
        <img src="rest.png" width="280" height="125" title="Restaurant" alt="Restaurant picture" />
        <br>
        <br>
        <a href="<?= serviceEscape($servicePage) ?>?restaurant=jamilah"> <button><?= serviceEscape($restaurants['jamilah']['name']) ?></button></a>
        </p1>
        <br>
        <hr>
        <p2>
        <img src="rest2.png" width="280" height="125" title="Restaurant" alt="Restaurant picture" />
        <br>
        <br>
        <a href="<?= serviceEscape($servicePage) ?>?restaurant=greenview"> <button><?= serviceEscape($restaurants['greenview']['name']) ?></button></a>
        <br>
        <hr>
        </p2>
        <p3>
        <img src="rest3.png" width="280" height="125" title="Restaurant" alt="Restaurant picture" />
        <br>
        <br>
        <a href="<?= serviceEscape($servicePage) ?>?restaurant=lepak"> <button><?= serviceEscape($restaurants['lepak']['name']) ?></button></a>
        <br>
        </br>
        </p>
        <?php endif; ?>
        </td>
       </form>
            </td>
        </tr>
    </table>

</body>

</html>
