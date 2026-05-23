<?php
require 'includes/config.php';
$connect = hms_db_connect();

$tablesToRename = [
    'tblpatient' => '_deprecated_tblpatient',
    'admin' => '_deprecated_admin',
    'tblcontactus' => '_deprecated_tblcontactus',
    'doctorslog' => '_deprecated_doctorslog',
    'userlog' => '_deprecated_userlog'
];

foreach ($tablesToRename as $old => $new) {
    // Check if old exists
    $check = $connect->query("SHOW TABLES LIKE '$old'");
    if ($check && $check->num_rows > 0) {
        $rename = $connect->query("RENAME TABLE `$old` TO `$new`");
        if ($rename) {
            echo "Successfully renamed $old to $new\n";
        } else {
            echo "Failed to rename $old: " . $connect->error . "\n";
        }
    } else {
        echo "Table $old does not exist or was already renamed.\n";
    }
}
