<?php
require 'includes/config.php';
$connect = hms_db_connect();
$tables = $connect->query("SHOW TABLES");
while ($row = $tables->fetch_row()) {
    $table = $row[0];
    echo "TABLE: $table\n";
    $cols = $connect->query("SHOW COLUMNS FROM `$table`");
    while ($c = $cols->fetch_assoc()) {
        echo "  - " . $c['Field'] . " (" . $c['Type'] . ")\n";
    }
}
