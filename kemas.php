<!--kemaskini.php-->
<!--To update data of restaurant.php into database.-->
<?php
include ("data.php");

extract ($_POST);

$query = "UPDATE commission SET commissions = '$commissions', penalties = '$penalties', services = '$services', totals = '$totals' WHERE ID = '$ID'";

$result = mysqli_query($conn,$query) or die ("Could not execute query in update.php");
if($result){
 echo "<script type = 'text/javascript'> window.location='restaurant.php' </script>";
}
?>