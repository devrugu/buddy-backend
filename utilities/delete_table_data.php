<?php
include '../database/db_connection.php';

$table_name_to_delete = "Countries";

$query = "DELETE FROM `$table_name_to_delete`;";

//mysqli_query($conn, $query);

mysqli_close($conn);