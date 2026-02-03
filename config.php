<?php
/**
 * Configuration file for KPN Validation Test API
 */

// Basic Auth credentials
define('AUTH_USERNAME', 'yossy');
define('AUTH_PASSWORD', 'yossy');

// Direktori untuk menyimpan file inbound
define('INBOUND_DIR', __DIR__ . '/inbound');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Pastikan folder inbound ada
if (!file_exists(INBOUND_DIR)) {
    mkdir(INBOUND_DIR, 0755, true);
}
