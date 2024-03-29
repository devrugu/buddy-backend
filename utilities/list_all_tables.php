<?php
header('Access-Control-Allow-Origin: *');
include '../database/db_connection.php';

// Tüm tabloları al
$tablesResult = $conn->query("SHOW TABLES");

if ($tablesResult->num_rows > 0) {
    while ($table = $tablesResult->fetch_row()) {
        $tableName = $table[0];
        echo "<h3>$tableName</h3>";

        // Sütun bilgilerini al
        $columnsSql = "SHOW FULL COLUMNS FROM $tableName";
        $columnsResult = $conn->query($columnsSql);

        if ($columnsResult->num_rows > 0) {
            echo "<ul>";
            while ($column = $columnsResult->fetch_assoc()) {
                // Sütun detaylarını hazırla
                $columnDetails = "{$column['Field']} ({$column['Type']})";
                if ($column['Key'] == 'PRI') {
                    $columnDetails .= " (PK)";
                }
                if ($column['Extra'] == 'auto_increment') {
                    $columnDetails .= " (AI)";
                }
                if ($column['Null'] == 'YES') {
                    $columnDetails .= " (NULL)";
                }

                // Foreign Key bilgilerini al
                $fkSql = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                          FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = 'buddy_database' AND TABLE_NAME = '$tableName' AND COLUMN_NAME = '{$column['Field']}'
                          AND REFERENCED_TABLE_NAME IS NOT NULL";
                $fkResult = $conn->query($fkSql);
                while ($fkRow = $fkResult->fetch_assoc()) {
                    $columnDetails .= " (FK=>{$fkRow['COLUMN_NAME']}-->{$fkRow['REFERENCED_TABLE_NAME']}::{$fkRow['REFERENCED_COLUMN_NAME']})";
                }

                echo "<li>$columnDetails</li>";
            }
            echo "</ul>";
        } else {
            echo "Sütun bilgisi bulunamadı.<br>";
        }
    }
} else {
    echo "Tablo bulunamadı.";
}

$conn->close();
?>
