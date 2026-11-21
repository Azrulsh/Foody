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
            <!--
                <button id=""style="float:bottom;" onclick="document.location=''">Main Page</button>
            -->
      
        <br>
        <a href="main.php"><button>Back</button></a>
        <p>SERVICE/REPORT LIST
        <p>
        <p1>DURATION:
        <a href="reportw.php"> <button>Weekly</button></a> 
        <button type="submit">Monthly</button>
        </p1>
        <br>
        <br>
        <p2>TYPE:
        <a href="servicem.php"> <button>Service</button></a>
        <button type="submit">Report</button>
        <br>
        <hr>
        </p2>
        <form> 
        <p>4756 Total Reports
        <br>
        <table border="1" align="center">
        <p> Week 1 
            <br>
            1313 Total Reports -RM:
            <input type="number">
            <p> Week 2
            <br>
            1143 Total Reports -RM:
            <input type="number">
            <p> Week 3
            <br>
            1098 Total Reports -RM:
            <input type="number">
            <p> Week 4
            <br>
            1195 Total Reports -RM:
            <input type="number" name="total">
            <br>
            <br>
             Total -RM:
             <input type="number" name="total">  
            <hr>
        </table> 
        <input type="reset" value="Delete">
        <a href="restaurant.php"> <button>Calculation</button></a> 
        </br>
        </p>
        </td>
       </form>
            </td>
        </tr>
    </table>

</body>

</html>