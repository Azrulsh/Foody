<!-- delete.php -->
<!-- To delete one particular data. -->

<?php

include ("data.php");

$IDURL = $_GET['ID'];

$query = "DELETE FROM commission WHERE ID = '$IDURL'";
$result = mysqli_query($conn,$query) or die ("Could not execute query in kemas.php");

if($result){
echo "<script type= 'text/javascript'> window.location='restaurant.php'</script>";
}
?>