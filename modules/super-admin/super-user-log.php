<?php
$pageTitle = 'Users';
$showDeleteAction = true;
$paymentsPath = '/modules/super-admin/super-Payments.php';
$addUserPath = '/modules/super-admin/Add-user.php';
$roleOptions = ['System Admin', 'Admin', 'Doctor', 'User'];

require_once __DIR__ . '/../shared/management/users.php';
