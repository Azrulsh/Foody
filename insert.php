<?php

include("data.php");

extract( $_POST );
$query = "INSERT INTO commission (commissions,penalties,services,totals) VALUES('$commissions','$penalties','$services','$totals')";

if (mysqli_query($conn, $query)) {
      
   echo "<script type='text/javascript'> window.location='restaurant.php' </script>";
	
} else {
    echo "Error: " . $query . "<br>" . mysqli_error($conn);
}

?>