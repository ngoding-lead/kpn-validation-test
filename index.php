<?php
/**
 * KPN Validation Test API
 * Domain: kpn-validation-test.ilmuprogram.app
 * Method: POST with Bearer Token Authentication
 * 
 * Menyimpan body POST ke folder inbound dalam format CSV
 */

require_once __DIR__ . '/config.php';

// Set headers
header('Content-Type: application/json');

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST is accepted.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Validasi Basic Auth
$username = $_SERVER['PHP_AUTH_USER'] ?? '';
$password = $_SERVER['PHP_AUTH_PW'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="KPN Validation API"');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($username !== AUTH_USERNAME || $password !== AUTH_PASSWORD) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Ambil body POST
$rawBody = file_get_contents('php://input');
$postData = json_decode($rawBody, true);

// Jika bukan JSON, coba ambil dari $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $postData = $_POST;
    if (empty($postData)) {
        // Simpan raw body jika bukan JSON dan bukan form data
        $postData = ['raw_body' => $rawBody];
    }
}

if (empty($postData) && empty($rawBody)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Request body is empty.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Generate nama file dengan timestamp
$timestamp = date('Y-m-d_H-i-s');
$uniqueId = uniqid();
$filename = "inbound_{$timestamp}_{$uniqueId}.csv";
$filepath = INBOUND_DIR . '/' . $filename;

// Siapkan data untuk CSV
$csvData = [];

// Tambahkan metadata
$metadata = [
    'received_at' => date('Y-m-d H:i:s'),
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
];

// Flatten nested array untuk CSV
function flattenArray($array, $prefix = '') {
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = $prefix ? "{$prefix}.{$key}" : $key;
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    return $result;
}

$flattenedData = array_merge(
    ['_metadata_received_at' => $metadata['received_at']],
    ['_metadata_remote_ip' => $metadata['remote_ip']],
    ['_metadata_user_agent' => $metadata['user_agent']],
    ['_metadata_content_type' => $metadata['content_type']],
    flattenArray($postData)
);

// Tulis ke CSV
try {
    $fp = fopen($filepath, 'w');
    
    if ($fp === false) {
        throw new Exception('Failed to create CSV file.');
    }
    
    // Tulis header
    fputcsv($fp, array_keys($flattenedData));
    
    // Tulis data
    fputcsv($fp, array_values($flattenedData));
    
    fclose($fp);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Data saved successfully.',
        'filename' => $filename,
        'timestamp' => date('Y-m-d H:i:s'),
        'data_received' => $postData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save data: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
