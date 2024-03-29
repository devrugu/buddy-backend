<?php
header('Access-Control-Allow-Origin: *');

include '../database/db_connection.php';

$tableName = $_GET['table'];

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sütun bilgilerini almak için kullanılan SQL sorgusu
$columnsSql = "SHOW FULL COLUMNS FROM $tableName";
$columnsResult = $conn->query($columnsSql);

if ($columnsResult->num_rows > 0) {
    echo "<table border='1'><tr>";
    while ($column = $columnsResult->fetch_assoc()) {
        // Sütun adı ve tipi
        echo "<th>" . $column['Field'] . " (" . $column['Type'] . ")";
        
        // Eğer sütun birincil anahtar ise
        if ($column['Key'] == 'PRI') {
            echo "(PK)";
        }
        
        // Eğer sütun otomatik artırma özelliğine sahipse
        if ($column['Extra'] == 'auto_increment') {
            echo "(AI)";
        }
        
        // Eğer sütun NULL değerlerini kabul ediyorsa
        if ($column['Null'] == 'YES') {
            echo "(NULL)";
        }
        
        echo "</th>";
    }
    echo "</tr>";

    // Tablo verilerini al
    $dataSql = "SELECT * FROM $tableName";
    $dataResult = $conn->query($dataSql);
    
    if ($dataResult->num_rows > 0) {
        while ($row = $dataResult->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='".$columnsResult->num_rows."'>0 results in $tableName</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching column information for $tableName";
}

$conn->close();
