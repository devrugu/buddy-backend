<?php
header('Access-Control-Allow-Origin: *');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Veritabanı Tabloları</title>
    <script>
        // AJAX ile tek bir tablonun verilerini yüklemek için fonksiyon
        function loadTableData(tableName) {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("tableData").innerHTML = this.responseText;
                }
            };
            xhttp.open("GET", "utilities/get_table_data.php?table=" + tableName, true);
            xhttp.send();
        }

        // AJAX ile tüm tabloların yapısını yüklemek için fonksiyon
        function loadAllTablesStructure() {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("tableData").innerHTML = this.responseText;
                }
            };
            xhttp.open("GET", "utilities/list_all_tables.php", true);
            xhttp.send();
        }
    </script>
</head>
<body>

<h1>Veritabanı Tabloları</h1>

<?php
require 'database/db_connection.php';

// Tüm tabloları al
$result = $conn->query("SHOW TABLES");

if ($result->num_rows > 0) {
    // Tabloları liste olarak yazdır
    while($table = $result->fetch_array()) {
        $tableName = $table[0];
        echo "<button onclick=\"loadTableData('$tableName')\">$tableName</button><br>";
    }
} else {
    echo "0 tables found";
}

// Bağlantıyı kapat
$conn->close();
?>

<!-- Tüm tablo yapılarını görüntülemek için buton -->
<button onclick="loadAllTablesStructure()">Tüm Tablo Yapılarını Görüntüle</button><br>

<div id="tableData">
    <!-- Tablo verileri veya yapı bilgileri burada gösterilecek -->
</div>

</body>
</html>
