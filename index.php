<?php
/**
 * KPN Validation Test API
 * Domain: kpn-validation-test.ilmuprogram.app
 * Method: POST (save data) / GET /data (list files)
 * 
 * Menyimpan body POST ke folder inbound dalam format JSON, XML, dan CSV
 * Data juga disimpan ke PostgreSQL database
 */

require_once __DIR__ . '/config.php';

// Parse URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$query = [];
parse_str(parse_url($requestUri, PHP_URL_QUERY) ?? '', $query);

// GET /data - Show HTML table from PostgreSQL (No auth required)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '/data' || $path === '/data/')) {
    // Check if file parameter is provided - return raw file
    if (isset($query['file'])) {
        header('Content-Type: application/json');
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
    
    // Check if viewing items for a specific header
    if (isset($query['items'])) {
        header('Content-Type: text/html; charset=UTF-8');
        $headerId = (int)$query['items'];
        $pdo = getDbConnection();
        
        if (!$pdo) {
            echo '<html><body><h1>Database connection error</h1></body></html>';
            exit;
        }
        
        // Get header info
        $headerStmt = $pdo->prepare("SELECT * FROM inbound_headers WHERE id = :id");
        $headerStmt->execute([':id' => $headerId]);
        $header = $headerStmt->fetch();
        
        if (!$header) {
            echo '<html><body><h1>Header not found</h1></body></html>';
            exit;
        }
        
        // Get items
        $itemsStmt = $pdo->prepare("SELECT * FROM inbound_items WHERE header_id = :header_id ORDER BY line_num ASC");
        $itemsStmt->execute([':header_id' => $headerId]);
        $items = $itemsStmt->fetchAll();
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items - Requisition #' . htmlspecialchars($header['requisition_id']) . '</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 10px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .header-info { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-info h2 { margin-top: 0; color: #333; }
        .header-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .header-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .header-item label { font-weight: 600; color: #666; font-size: 12px; display: block; margin-bottom: 4px; }
        .header-item span { color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #007bff; color: #fff; font-weight: 600; position: sticky; top: 0; }
        tr:hover { background: #f8f9fa; }
        .number { text-align: right; font-family: monospace; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .file-links { margin-top: 15px; }
        .file-links a { display: inline-block; margin-right: 10px; padding: 8px 16px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .file-links a:hover { background: #218838; }
        .file-links a.xml { background: #fd7e14; }
        .file-links a.xml:hover { background: #e76b00; }
        .file-links a.csv { background: #17a2b8; }
        .file-links a.csv:hover { background: #138496; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link"><a href="/data">&larr; Back to Data List</a></div>
        <h1>Requisition #' . htmlspecialchars($header['requisition_id']) . '</h1>
        
        <div class="header-info">
            <h2>Header Information</h2>
            <div class="header-grid">
                <div class="header-item"><label>Status</label><span>' . htmlspecialchars($header['status'] ?? '-') . '</span></div>
                <div class="header-item"><label>Received At</label><span>' . htmlspecialchars($header['received_at']) . '</span></div>
                <div class="header-item"><label>Submitted At</label><span>' . htmlspecialchars($header['submitted_at'] ?? '-') . '</span></div>
                <div class="header-item"><label>Requested By</label><span>' . htmlspecialchars($header['requested_by_login'] ?? '-') . '</span></div>
                <div class="header-item"><label>Ship To</label><span>' . htmlspecialchars($header['ship_to_address_name'] ?? '-') . '</span></div>
                <div class="header-item"><label>City</label><span>' . htmlspecialchars($header['ship_to_address_city'] ?? '-') . '</span></div>
                <div class="header-item"><label>Total</label><span>' . htmlspecialchars($header['currency_code'] ?? '') . ' ' . number_format((float)$header['total'], 2) . '</span></div>
                <div class="header-item"><label>Remote IP</label><span>' . htmlspecialchars($header['remote_ip'] ?? '-') . '</span></div>
            </div>
            <div class="file-links">
                <a href="/data?file=' . urlencode($header['json_filename']) . '" target="_blank">📄 View JSON</a>
                <a href="/data?file=' . urlencode($header['xml_filename']) . '" class="xml" target="_blank">📄 View XML</a>
                <a href="/data?file=' . urlencode($header['csv_filename']) . '" class="csv" target="_blank">📄 View CSV</a>
            </div>
        </div>
        
        <h2>Line Items (' . count($items) . ')</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Number</th>
                    <th>Description</th>
                    <th>Supplier</th>
                    <th>Account</th>
                    <th>Qty</th>
                    <th>UOM</th>
                    <th class="number">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        if (empty($items)) {
            echo '<tr><td colspan="8" style="text-align:center;color:#666;">No items found</td></tr>';
        } else {
            foreach ($items as $item) {
                echo '<tr>
                    <td>' . htmlspecialchars($item['line_num']) . '</td>
                    <td>' . htmlspecialchars($item['item_number'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($item['description'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($item['supplier_name'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($item['account_code'] ?? '-') . ' - ' . htmlspecialchars($item['account_name'] ?? '') . '</td>
                    <td class="number">' . number_format((float)$item['quantity'], 2) . '</td>
                    <td>' . htmlspecialchars($item['uom_code'] ?? '-') . '</td>
                    <td class="number">' . number_format((float)$item['total'], 2) . '</td>
                </tr>';
            }
        }
        
        echo '</tbody>
        </table>
    </div>
</body>
</html>';
        exit;
    }
    
    // Check if viewing approvals for a specific header
    if (isset($query['approvals'])) {
        header('Content-Type: text/html; charset=UTF-8');
        $headerId = (int)$query['approvals'];
        $pdo = getDbConnection();
        
        if (!$pdo) {
            echo '<html><body><h1>Database connection error</h1></body></html>';
            exit;
        }
        
        // Get header info
        $headerStmt = $pdo->prepare("SELECT * FROM inbound_headers WHERE id = :id");
        $headerStmt->execute([':id' => $headerId]);
        $header = $headerStmt->fetch();
        
        if (!$header) {
            echo '<html><body><h1>Header not found</h1></body></html>';
            exit;
        }
        
        // Get approvals
        $approvalsStmt = $pdo->prepare("SELECT * FROM inbound_approvals WHERE header_id = :header_id ORDER BY position ASC");
        $approvalsStmt->execute([':header_id' => $headerId]);
        $approvals = $approvalsStmt->fetchAll();
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Chain - Requisition #' . htmlspecialchars($header['requisition_id']) . '</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 10px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .header-info { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-info h2 { margin-top: 0; color: #333; }
        .header-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .header-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .header-item label { font-weight: 600; color: #666; font-size: 12px; display: block; margin-bottom: 4px; }
        .header-item span { color: #333; }
        .approval-chain { display: flex; flex-direction: column; gap: 0; }
        .approval-step { display: flex; align-items: flex-start; gap: 20px; }
        .approval-connector { display: flex; flex-direction: column; align-items: center; width: 40px; }
        .approval-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 16px; }
        .approval-circle.pending { background: #ffc107; color: #333; }
        .approval-circle.approved { background: #28a745; }
        .approval-circle.rejected { background: #dc3545; }
        .approval-line { width: 3px; height: 60px; background: #dee2e6; }
        .approval-content { flex: 1; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .approval-content h3 { margin: 0 0 10px 0; color: #333; font-size: 16px; }
        .approval-content .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .approval-content .status.pending_approval { background: #fff3cd; color: #856404; }
        .approval-content .status.approved { background: #d4edda; color: #155724; }
        .approval-content .status.rejected { background: #f8d7da; color: #721c24; }
        .approval-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px; font-size: 14px; }
        .approval-details .label { color: #666; }
        .approval-details .value { color: #333; }
        .empty-approvals { background: #fff; padding: 40px; text-align: center; border-radius: 8px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link"><a href="/data">&larr; Back to Data List</a></div>
        <h1>🔗 Approval Chain - Requisition #' . htmlspecialchars($header['requisition_id']) . '</h1>
        
        <div class="header-info">
            <h2>Requisition Information</h2>
            <div class="header-grid">
                <div class="header-item"><label>Status</label><span>' . htmlspecialchars($header['status'] ?? '-') . '</span></div>
                <div class="header-item"><label>Requested By</label><span>' . htmlspecialchars($header['requested_by_login'] ?? '-') . '</span></div>
                <div class="header-item"><label>Submitted At</label><span>' . htmlspecialchars($header['submitted_at'] ?? '-') . '</span></div>
                <div class="header-item"><label>Total</label><span>' . htmlspecialchars($header['currency_code'] ?? '') . ' ' . number_format((float)$header['total'], 2) . '</span></div>
            </div>
        </div>
        
        <h2>Approval Steps (' . count($approvals) . ')</h2>';
        
        if (empty($approvals)) {
            echo '<div class="empty-approvals">No approval chain data found for this requisition.</div>';
        } else {
            echo '<div class="approval-chain">';
            $totalApprovals = count($approvals);
            foreach ($approvals as $index => $approval) {
                $statusClass = strtolower(str_replace(' ', '_', $approval['status'] ?? 'pending'));
                $circleClass = 'pending';
                if ($approval['status'] === 'approved') $circleClass = 'approved';
                if ($approval['status'] === 'rejected') $circleClass = 'rejected';
                
                echo '<div class="approval-step">
                    <div class="approval-connector">
                        <div class="approval-circle ' . $circleClass . '">' . htmlspecialchars($approval['position']) . '</div>
                        ' . ($index < $totalApprovals - 1 ? '<div class="approval-line"></div>' : '') . '
                    </div>
                    <div class="approval-content">
                        <h3>Step ' . htmlspecialchars($approval['position']) . ' - ' . htmlspecialchars(ucwords(str_replace('_', ' ', $approval['status'] ?? 'Pending'))) . '</h3>
                        <span class="status ' . $statusClass . '">' . htmlspecialchars(ucwords(str_replace('_', ' ', $approval['status'] ?? '-'))) . '</span>
                        <div class="approval-details">
                            <div><span class="label">Approval ID:</span></div>
                            <div><span class="value">' . htmlspecialchars($approval['approval_id'] ?? '-') . '</span></div>
                            <div><span class="label">Approval Chain ID:</span></div>
                            <div><span class="value">' . htmlspecialchars($approval['approval_chain_id'] ?? '-') . '</span></div>
                        </div>
                    </div>
                </div>';
            }
            echo '</div>';
        }
        
        echo '
    </div>
</body>
</html>';
        exit;
    }
    
    // Show main data table from PostgreSQL
    header('Content-Type: text/html; charset=UTF-8');
    
    $pdo = getDbConnection();
    $headers = [];
    $dbError = null;
    
    if ($pdo) {
        try {
            // Only show records with item_count > 0
            $stmt = $pdo->query("SELECT h.*, 
                (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) as item_count,
                (SELECT COUNT(*) FROM inbound_approvals WHERE header_id = h.id) as approval_count
                FROM inbound_headers h 
                WHERE (SELECT COUNT(*) FROM inbound_items WHERE header_id = h.id) > 0
                ORDER BY h.received_at DESC");
            $headers = $stmt->fetchAll();
        } catch (PDOException $e) {
            $dbError = $e->getMessage();
        }
    } else {
        $dbError = "Could not connect to database";
    }
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPN Validation - Inbound Data</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { margin: 0 0 5px 0; color: #666; font-size: 14px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #007bff; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .table-wrapper { overflow-x: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #007bff; color: #fff; font-weight: 600; position: sticky; top: 0; white-space: nowrap; }
        tr:hover { background: #f8f9fa; }
        .number { text-align: right; font-family: monospace; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status.pending_approval { background: #fff3cd; color: #856404; }
        .status.approved { background: #d4edda; color: #155724; }
        .status.rejected { background: #f8d7da; color: #721c24; }
        .actions { white-space: nowrap; }
        .actions a { display: inline-block; margin-right: 5px; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; }
        .actions .view-items { background: #007bff; color: #fff; }
        .actions .view-items:hover { background: #0056b3; }
        .actions .view-approvals { background: #6f42c1; color: #fff; }
        .actions .view-approvals:hover { background: #5a32a3; }
        .actions .view-json { background: #28a745; color: #fff; }
        .actions .view-json:hover { background: #218838; }
        .actions .view-xml { background: #fd7e14; color: #fff; }
        .actions .view-xml:hover { background: #e76b00; }
        .actions .view-csv { background: #17a2b8; color: #fff; }
        .actions .view-csv:hover { background: #138496; }
        .empty { text-align: center; padding: 40px; color: #666; }
        .truncate { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 KPN Validation - Inbound Data</h1>
        <p class="subtitle">Data from PostgreSQL database (sorted by newest first)</p>';
    
    if ($dbError) {
        echo '<div class="error"><strong>Database Error:</strong> ' . htmlspecialchars($dbError) . '</div>';
    }
    
    echo '<div class="stats">
            <div class="stat-card">
                <h3>Total Records</h3>
                <div class="value">' . count($headers) . '</div>
            </div>
        </div>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Received At</th>
                        <th>Requisition ID</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Ship To</th>
                        <th class="number">Total</th>
                        <th>Currency</th>
                        <th>Items</th>
                        <th>Approvals</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (empty($headers)) {
        echo '<tr><td colspan="11" class="empty">No data found. Send POST requests to add data.</td></tr>';
    } else {
        foreach ($headers as $row) {
            $statusClass = strtolower(str_replace(' ', '_', $row['status'] ?? ''));
            echo '<tr>
                <td>' . htmlspecialchars($row['id']) . '</td>
                <td>' . htmlspecialchars($row['received_at']) . '</td>
                <td>' . htmlspecialchars($row['requisition_id'] ?? '-') . '</td>
                <td><span class="status ' . $statusClass . '">' . htmlspecialchars($row['status'] ?? '-') . '</span></td>
                <td>' . htmlspecialchars($row['requested_by_login'] ?? '-') . '</td>
                <td class="truncate" title="' . htmlspecialchars($row['ship_to_address_name'] ?? '') . '">' . htmlspecialchars($row['ship_to_address_name'] ?? '-') . '</td>
                <td class="number">' . number_format((float)$row['total'], 2) . '</td>
                <td>' . htmlspecialchars($row['currency_code'] ?? '-') . '</td>
                <td class="number">' . htmlspecialchars($row['item_count']) . '</td>
                <td class="number">' . htmlspecialchars($row['approval_count'] ?? 0) . '</td>
                <td class="actions">
                    <a href="/data?items=' . $row['id'] . '" class="view-items">📋 Items</a>
                    <a href="/data?approvals=' . $row['id'] . '" class="view-approvals">🔗 Approvals</a>
                    <a href="/data?file=' . urlencode($row['json_filename']) . '" class="view-json" target="_blank">JSON</a>
                </td>
            </tr>';
        }
    }
    
    echo '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';
    exit;
}

// GET /data/{filename} - Download/view file (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/data/(.+)$#', $path, $matches)) {
    header('Content-Type: application/json');
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

// Set JSON header for other routes
header('Content-Type: application/json');

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
    
    // Save to PostgreSQL database
    $dbResult = saveToDatabase($metadata, $postData, $jsonFilename, $xmlFilename, $csvFilename);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Data saved successfully.',
        'database_id' => $dbResult ?: null,
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
