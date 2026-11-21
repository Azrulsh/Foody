<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method not allowed.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Help Center - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="2" style="width:160px;height:700px"><?php require __DIR__ . '/sidebar.php'; ?></td>
            <td style="width:1000px;text-align:center"><header>HELP CENTER</header></td>
        </tr>
        <tr><td class="profile-content">
            <main class="help-guide">
                <h1>Using Foody</h1>
                <p>Use the sidebar to open Main Page, My profile or My Restaurant. Changes are saved only when you submit the relevant form.</p>

                <section aria-labelledby="help-service-report">
                    <h2 id="help-service-report">Service and Report</h2>
                    <ol>
                        <li>Open <a href="main.php">Main Page</a> and select a restaurant.</li>
                        <li>Choose Weekly or Monthly, then Service or Report.</li>
                        <li>Enter the RM amount for each Day or Week. Enter 0 for slots with no amount.</li>
                        <li>Check Total RM, then click Calculation to save the amounts and open the commission page.</li>
                    </ol>
                    <p>Amounts accept up to two decimal places, such as 10, 10.5 or 10.50. Clicking an input showing 0 selects it so you can type a replacement.</p>
                    <p>Typing updates the displayed total but does not save. Reopening the page loads the last saved amounts. Each restaurant has separate Weekly and Monthly values, and Service data is separate from Report data.</p>
                    <p>The Services Completed and Total Reports counts are sample figures. They are not calculated from saved RM amounts. Weekly and Monthly currently store one set of values each; they do not keep dated history for different weeks or months.</p>
                </section>

                <section aria-labelledby="help-commission">
                    <h2 id="help-commission">Commission</h2>
                    <p>After Calculation, enter Total Commission RM, Penalties RM and Delivery per Services. Delivery per Services means the total delivery cost in RM for the selected period, rather than a delivery count or rate per delivery.</p>
                    <p><strong>NETT Commission = Total Commission − Penalties − Total Delivery Cost</strong></p>
                    <p>Example: RM100 − RM10 − RM5 = RM85.00. NETT is calculated automatically and cannot be edited. If deductions exceed commission, NETT is negative.</p>
                    <ul>
                        <li><strong>Add:</strong> create the commission record for the selected restaurant, Service/Report and Weekly/Monthly combination.</li>
                        <li><strong>Update:</strong> change an existing commission record. If none exists, use Add first.</li>
                        <li><strong>Back:</strong> return to the Service or Report page used to open the commission page.</li>
                    </ul>
                    <p>Total Commission is entered manually. The RM total from Service or Report is not automatically treated as commission.</p>
                </section>

                <section aria-labelledby="help-reset">
                    <h2 id="help-reset">Saving and Delete</h2>
                    <p>Calculation saves Service/Report amounts. Add and Update save commission values. Save stores changes in My Profile or My Restaurant. Leaving a page before submitting its form discards unsaved changes.</p>
                    <p><strong>Delete resets the form to the values loaded when the page opened.</strong> It does not delete saved database records.</p>
                </section>

                <section aria-labelledby="help-profile">
                    <h2 id="help-profile">My Profile and login</h2>
                    <p>Open <a href="profile.php">My profile</a> to update your name or email, or change your password, then click Save. Changing email or password requires your current password. Leave the new password fields blank to keep your password.</p>
                    <p>New passwords must be 12 to 72 bytes; for ordinary English letters, numbers and symbols this means 12 to 72 characters. Enter the same new password in both password fields.</p>
                    <p>After changing email or password, other existing login sessions are signed out. Use your updated email and password for future logins. Log Out in the sidebar signs out this browser.</p>
                </section>

                <section aria-labelledby="help-restaurants">
                    <h2 id="help-restaurants">My Restaurant</h2>
                    <p>Open <a href="my_restaurants.php">My Restaurant</a> to edit the name, address and phone number of one of the three existing restaurants, then click that restaurant's Save button. Address and phone number can be left blank.</p>
                    <p>Renaming a restaurant updates its name on Main Page and keeps its Service, Report and Commission records connected. Restaurant images remain the same. Adding and deleting restaurants are not available.</p>
                    <p>All signed-in users share access to these restaurants and records. Order List and Settings are not yet available.</p>
                </section>

                <section aria-labelledby="help-errors">
                    <h2 id="help-errors">Common problems</h2>
                    <dl>
                        <dt><strong>Returned to Login</strong></dt>
                        <dd>Sign in again. Sessions expire after 30 minutes of inactivity or 12 hours, and older sessions are revoked after an email or password change. Reopen any unsaved forms after signing in.</dd>
                        <dt><strong>Too many attempts</strong></dt>
                        <dd>Wait up to 15 minutes before trying again. Check that you are using the correct email and password.</dd>
                        <dt><strong>Form expired</strong></dt>
                        <dd>Reload the page to obtain a fresh form, check its values and submit again.</dd>
                        <dt><strong>Invalid amount or details</strong></dt>
                        <dd>Check the highlighted browser field or displayed message. Money inputs must be non-negative, no more than 9999999.99 and have up to two decimal places. NETT may be negative because it is calculated after deductions.</dd>
                        <dt><strong>Record has changed</strong></dt>
                        <dd>Another tab or user saved a newer version. Reload the page, review the latest data and reapply your intended changes.</dd>
                        <dt><strong>Commission already exists</strong></dt>
                        <dd>Reload the commission page and use Update instead of Add.</dd>
                        <dt><strong>Data or login unavailable</strong></dt>
                        <dd>Try again later or contact the person maintaining this installation. After access is restored, reopen the page to check the last saved values before resubmitting.</dd>
                    </dl>
                </section>
            </main>
        </td></tr>
    </table>
</body>
</html>
