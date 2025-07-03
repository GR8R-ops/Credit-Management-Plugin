<?php

require_once 'GR8R_Admin.php';

$admin = new GR8R_Admin();

// Simulate a GET request
$_GET['page'] = 'balances'; // Change to 'dashboard', 'transactions', etc.

$admin->handleRequest();
