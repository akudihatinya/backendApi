<?php

use Illuminate\Support\Facades\Route;

// Import semua route api dari direktori api
require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/puskesmas.php';
require __DIR__ . '/api/dashboard.php';
require __DIR__ . '/api/statistics.php';
require __DIR__ . '/api/maintenance.php';