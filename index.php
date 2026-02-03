<?php
/**
 * KPN Validation Test API
 * Domain: kpn-validation-test.ilmuprogram.app
 * Method: POST (save data) / GET /data (list files)
 * 
 * Menyimpan body POST ke folder inbound dalam format JSON, XML, dan CSV
 */

require_once __DIR__ . '/config.php';

// Set headers
header('Content-Type: application/json');

// Parse URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$query = [];
parse_str(parse_url($requestUri, PHP_URL_QUERY) ?? '', $query);

// GET /data - List all files
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '/data' || $path === '/data/')) {
    // Check if file parameter is provided
    if (isset($query['file'])) {
        $filename = basename($query['file']);
        $filepath = INBOUND_DIR . '/' . $filename;
        
        if (!file_exists($filepath) || $filename === '.gitkeep') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $contentTypes = [
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv'
        ];
        
        header('Content-Type: ' . ($contentTypes[$ext] ?? 'text/plain'));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        readfile($filepath);
        exit;
    }
    
    // List all files
    $files = [];
    $inboundFiles = glob(INBOUND_DIR . '/*.*');
    
    foreach ($inboundFiles as $file) {
        $filename = basename($file);
        if ($filename === '.gitkeep') continue;
        
        $files[] = [
            'filename' => $filename,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'url' => '/data?file=' . urlencode($filename)
        ];
    }
    
    // Sort by modified desc
    usort($files, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
    
    echo json_encode([
        'success' => true,
        'total' => count($files),
        'files' => $files
    ], JSON_PRETTY_PRINT);
    exit;
}

// GET /data/{filename} - Download/view file (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/data/(.+)$#', $path, $matches)) {
    $filename = basename($matches[1]);
    $filepath = INBOUND_DIR . '/' . $filename;
    
    if (!file_exists($filepath) || $filename === '.gitkeep') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $contentTypes = [
        'json' => 'application/json',
        'xml' => 'application/xml',
        'csv' => 'text/csv'
    ];
    
    header('Content-Type: ' . ($contentTypes[$ext] ?? 'text/plain'));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    readfile($filepath);
    exit;
}

// Hanya terima method POST untuk save data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST to save data or GET /data to list files.',
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
$baseFilename = "inbound_{$timestamp}_{$uniqueId}";

// Siapkan metadata
$metadata = [
    'received_at' => date('Y-m-d H:i:s'),
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
];

// 1. Simpan raw body sebagai JSON (original)
$jsonFilename = $baseFilename . '.json';
$jsonFilepath = INBOUND_DIR . '/' . $jsonFilename;
$jsonContent = json_encode([
    '_metadata' => $metadata,
    'data' => $postData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($jsonFilepath, $jsonContent);

// 2. Simpan sebagai XML
$xmlFilename = $baseFilename . '.xml';
$xmlFilepath = INBOUND_DIR . '/' . $xmlFilename;

function arrayToXml($data, &$xml, $parentKey = '') {
    foreach ($data as $key => $value) {
        // Handle numeric keys
        $elementName = is_numeric($key) ? 'item_' . $key : $key;
        // Clean element name (remove invalid XML chars)
        $elementName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $elementName);
        
        if (is_array($value)) {
            $child = $xml->addChild($elementName);
            arrayToXml($value, $child, $elementName);
        } else {
            $xml->addChild($elementName, htmlspecialchars((string)$value));
        }
    }
}

$xmlData = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><inbound></inbound>');
arrayToXml(['_metadata' => $metadata, 'data' => $postData], $xmlData);
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xmlData->asXML());
file_put_contents($xmlFilepath, $dom->saveXML());

// 3. Simpan sebagai CSV (flattened)
$csvFilename = $baseFilename . '.csv';
$csvFilepath = INBOUND_DIR . '/' . $csvFilename;

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
    $fp = fopen($csvFilepath, 'w');
    
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
        'files' => [
            'json' => $jsonFilename,
            'xml' => $xmlFilename,
            'csv' => $csvFilename
        ],
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
