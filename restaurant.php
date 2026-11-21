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
            <a href="servicew.php"> <button>Back</button></a>   
            <?php
                    include("data.php");
                    $query = "SELECT * FROM commission";
                    $result = mysqli_query($conn,$query);
                    $row = mysqli_fetch_assoc($result);
                    $commissions = $row["commissions"];
                    $penalties = $row["penalties"];
                    $services = $row["services"];
                    $totals = $row["totals"];
                    ?>
                    <form action="kemas.php" method="post"> 
        <br>
        <p1>TOTAL COMMISSION RM:
        <input type="number" id="fname" name="commissions" value="<?php echo $commissions ?>"><br>
</p1>
        <br>
        <p2>PENALTIES RM:
        <input type="number" id="fname" name="penalties" value="<?php echo $penalties ?>"><br>
</p2>
        <br>
        <p3>DELIVERY PER SERVICES:
        <input type="number" id="fname" name="services" value="<?php echo $services ?>"><br>
</p3>
        <br>
        <p4>NETT COMMISSION RM:
        <input type="text" id="fname" name="totals" value="<?php echo $totals ?>"><br>
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