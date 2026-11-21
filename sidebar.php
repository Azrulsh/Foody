<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
?>
    <style>
        .sidebar-cell {
            vertical-align: top;
            height: max(700px, calc(50vh + var(--sidebar-height, 460px) / 2));
            height: max(700px, calc(50dvh + var(--sidebar-height, 460px) / 2));
        }
        .sidebar-sticky {
            position: sticky;
            top: max(12px, calc((100vh - var(--sidebar-height, 460px)) / 2));
            top: max(12px, calc((100dvh - var(--sidebar-height, 460px)) / 2));
            max-height: calc(100vh - 24px);
            max-height: calc(100dvh - 24px);
            overflow-y: auto;
        }
        #sidebar{
            background-color: black;
            border-color: 1px black;
        }
        #sidebarbox{
            background-color: white;
            color: black;
            text-align: center;
        }
        #sidebutton{
            background-color: white;
            color: black;
            border:none;
            cursor: pointer;
            text-decoration: none;
            padding: 10px 20px;
            width: 150px;
        }
        #sidebutton:hover{
            background-color: grey;
        }
    
        
    </style>
    <div class="sidebar-sticky">
        <table id="sidebar">
            <tr>
                <td>
                    <img src="Logo.png" alt="logo">
                </td>
            </tr>
            <tr>
                <td style="color:white ;text-align:center" > 
                <br>
                <span class="user-name"><?= serviceEscape($currentUser['name']) ?></span>
                <br>
                <br>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='profile.php'">My profile</button>
                </td> 
            </tr>
            <tr> 
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='main.php'">Main Page</button>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='my_restaurants.php'">My Restaurant</button>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='orders.php'">Order List</button>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='settings.php'">Settings</button>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <button type="button" id="sidebutton" onclick="document.location='help.php'">Help Center</button>
                </td>
            </tr>
            <tr>
                <td id="sidebarbox">
                <form action="logout.php" method="post" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                    <button type="submit" id="sidebutton">Log Out</button>
                </form>
                </td>
            </tr>
            <tr>
                <td style="color:white ;text-align:center">
                    ver.2.0
                </td>
            </tr>
        </table>
    </div>
    <script src="sidebar.js" defer></script>
