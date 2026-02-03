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

// PostgreSQL Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'kpn_validation_test');
define('DB_USER', 'postgres');
define('DB_PASS', 'Pas671_ok'); // PostgreSQL password

// SAP RFC Configuration
define('SAP_ASHOST', '192.168.1.103');    // Application Server
define('SAP_SYSNR', '10');                // Instance Number
define('SAP_SYSID', 'SBX');               // System ID
define('SAP_CLIENT', '777');              // Client
define('SAP_USER', 'wahyu.amaldi');       // SAP Username
define('SAP_PASS', 'Pas671_ok12345');     // SAP Password
define('SAP_LANG', 'EN');                 // Language

/**
 * Get SAP RFC Connection
 */
function getSapConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $config = [
                'ashost' => SAP_ASHOST,
                'sysnr'  => SAP_SYSNR,
                'client' => SAP_CLIENT,
                'user'   => SAP_USER,
                'passwd' => SAP_PASS,
                'lang'   => SAP_LANG
            ];
            
            $connection = new SAPNWRFC\Connection($config);
        } catch (Exception $e) {
            error_log("SAP RFC connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $connection;
}

/**
 * Record RFC call to history table
 */
function recordRfcHistory($headerId, $functionModule, $requestData, $responseData, $success, $errorMessage = null, $executionTimeMs = 0) {
    $pdo = getDbConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "INSERT INTO rfc_call_history 
                (header_id, function_module, request_data, response_data, success, error_message, execution_time_ms)
                VALUES (:header_id, :function_module, :request_data, :response_data, :success, :error_message, :execution_time_ms)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':header_id' => $headerId,
            ':function_module' => $functionModule,
            ':request_data' => json_encode($requestData),
            ':response_data' => json_encode($responseData),
            ':success' => $success ? 't' : 'f',
            ':error_message' => $errorMessage,
            ':execution_time_ms' => $executionTimeMs
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to record RFC history: " . $e->getMessage());
        return false;
    }
}

/**
 * Call SAP RFC Function Module ZKPN_TEST
 * @param array $tData - Array of data with requested_by_id and line_id
 * @param int $headerId - Header ID for recording history
 * @return array - Result from SAP
 */
function callZkpnTest($tData, $headerId = null) {
    $startTime = microtime(true);
    $connection = getSapConnection();
    
    if (!$connection) {
        $result = [
            'success' => false,
            'error' => 'SAP connection failed'
        ];
        if ($headerId) {
            recordRfcHistory($headerId, 'ZKPN_TEST', $tData, $result, false, 'SAP connection failed', 0);
        }
        return $result;
    }
    
    try {
        // Get function module
        $function = $connection->getFunction('ZKPN_TEST');
        
        // Prepare T_DATA table
        $tableData = [];
        foreach ($tData as $row) {
            $tableData[] = [
                'REQUESTED_BY_ID' => (string)($row['requested_by_id'] ?? ''),
                'LINE_ID' => (string)($row['line_id'] ?? '')
            ];
        }
        
        // Invoke function
        $rfcResult = $function->invoke([
            'T_DATA' => $tableData
        ]);
        
        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $result = [
            'success' => true,
            'result' => $rfcResult
        ];
        
        // Record to history
        if ($headerId) {
            recordRfcHistory($headerId, 'ZKPN_TEST', ['T_DATA' => $tableData], $rfcResult, true, null, $executionTime);
        }
        
        return $result;
        
    } catch (SAPNWRFC\FunctionCallException $e) {
        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        error_log("SAP RFC call error: " . $e->getMessage());
        
        $result = [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode()
        ];
        
        if ($headerId) {
            recordRfcHistory($headerId, 'ZKPN_TEST', ['T_DATA' => $tableData ?? []], $result, false, $e->getMessage(), $executionTime);
        }
        
        return $result;
    } catch (Exception $e) {
        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        error_log("SAP RFC error: " . $e->getMessage());
        
        $result = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        
        if ($headerId) {
            recordRfcHistory($headerId, 'ZKPN_TEST', ['T_DATA' => $tableData ?? []], $result, false, $e->getMessage(), $executionTime);
        }
        
        return $result;
    }
}

/**
 * Call ZKPN_TEST for a specific header (all its items)
 * @param int $headerId - Header ID from database
 * @return array - Result
 */
function callZkpnTestForHeader($headerId) {
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['success' => false, 'error' => 'Database connection failed'];
    }
    
    // Get header and items data
    $sql = "SELECT h.requested_by_id, i.line_id 
            FROM inbound_headers h 
            JOIN inbound_items i ON i.header_id = h.id 
            WHERE h.id = :header_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':header_id' => $headerId]);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        return ['success' => false, 'error' => 'No data found for header ID: ' . $headerId];
    }
    
    // Call RFC with header_id for history recording
    return callZkpnTest($rows, $headerId);
}

/**
 * Get PDO database connection
 */
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

/**
 * Save inbound data to PostgreSQL
 */
function saveToDatabase($metadata, $postData, $jsonFilename, $xmlFilename, $csvFilename) {
    $pdo = getDbConnection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Extract header data
        $data = $postData;
        $fileId = pathinfo($jsonFilename, PATHINFO_FILENAME);
        
        $headerSql = "INSERT INTO inbound_headers (
            file_id, received_at, remote_ip, user_agent, requisition_id, 
            created_at, updated_at, status, submitted_at, ship_to_attention,
            exported, total, total_with_estimated_tax, estimated_tax_amount, 
            rejected, currency_code, requested_by_id, requested_by_login,
            ship_to_address_id, ship_to_address_name, ship_to_address_city,
            ship_to_address_street1, buyer_note, justification,
            json_filename, xml_filename, csv_filename
        ) VALUES (
            :file_id, :received_at, :remote_ip, :user_agent, :requisition_id,
            :created_at, :updated_at, :status, :submitted_at, :ship_to_attention,
            :exported, :total, :total_with_estimated_tax, :estimated_tax_amount,
            :rejected, :currency_code, :requested_by_id, :requested_by_login,
            :ship_to_address_id, :ship_to_address_name, :ship_to_address_city,
            :ship_to_address_street1, :buyer_note, :justification,
            :json_filename, :xml_filename, :csv_filename
        ) RETURNING id";
        
        $stmt = $pdo->prepare($headerSql);
        $stmt->execute([
            ':file_id' => $fileId,
            ':received_at' => $metadata['received_at'],
            ':remote_ip' => $metadata['remote_ip'],
            ':user_agent' => $metadata['user_agent'],
            ':requisition_id' => $data['id'] ?? null,
            ':created_at' => isset($data['created-at']) ? date('Y-m-d H:i:s', strtotime($data['created-at'])) : null,
            ':updated_at' => isset($data['updated-at']) ? date('Y-m-d H:i:s', strtotime($data['updated-at'])) : null,
            ':status' => $data['status'] ?? null,
            ':submitted_at' => isset($data['submitted-at']) ? date('Y-m-d H:i:s', strtotime($data['submitted-at'])) : null,
            ':ship_to_attention' => $data['ship-to-attention'] ?? null,
            ':exported' => isset($data['exported']) ? ($data['exported'] ? 't' : 'f') : 'f',
            ':total' => $data['total'] ?? null,
            ':total_with_estimated_tax' => $data['total-with-estimated-tax'] ?? null,
            ':estimated_tax_amount' => $data['estimated-tax-amount'] ?? null,
            ':rejected' => isset($data['rejected']) ? ($data['rejected'] ? 't' : 'f') : 'f',
            ':currency_code' => $data['currency']['code'] ?? null,
            ':requested_by_id' => $data['requested-by']['id'] ?? null,
            ':requested_by_login' => $data['requested-by']['login'] ?? null,
            ':ship_to_address_id' => $data['ship-to-address']['id'] ?? null,
            ':ship_to_address_name' => $data['ship-to-address']['name'] ?? null,
            ':ship_to_address_city' => $data['ship-to-address']['city'] ?? null,
            ':ship_to_address_street1' => $data['ship-to-address']['street1'] ?? null,
            ':buyer_note' => $data['buyer-note'] ?? null,
            ':justification' => $data['justification'] ?? null,
            ':json_filename' => $jsonFilename,
            ':xml_filename' => $xmlFilename,
            ':csv_filename' => $csvFilename
        ]);
        
        $headerId = $stmt->fetchColumn();
        
        // Insert items (requisition-lines)
        if (!empty($data['requisition-lines'])) {
            $itemSql = "INSERT INTO inbound_items (
                header_id, line_id, line_num, description, quantity, total,
                source_part_num, status, item_id, item_number, item_name,
                supplier_id, supplier_name, supplier_number, uom_code,
                account_id, account_name, account_code, created_at
            ) VALUES (
                :header_id, :line_id, :line_num, :description, :quantity, :total,
                :source_part_num, :status, :item_id, :item_number, :item_name,
                :supplier_id, :supplier_name, :supplier_number, :uom_code,
                :account_id, :account_name, :account_code, :created_at
            )";
            
            $itemStmt = $pdo->prepare($itemSql);
            
            foreach ($data['requisition-lines'] as $line) {
                $itemStmt->execute([
                    ':header_id' => $headerId,
                    ':line_id' => $line['id'] ?? null,
                    ':line_num' => $line['line-num'] ?? null,
                    ':description' => $line['description'] ?? null,
                    ':quantity' => $line['quantity'] ?? null,
                    ':total' => $line['total'] ?? null,
                    ':source_part_num' => $line['source-part-num'] ?? null,
                    ':status' => $line['status'] ?? null,
                    ':item_id' => $line['item']['id'] ?? null,
                    ':item_number' => $line['item']['item-number'] ?? null,
                    ':item_name' => $line['item']['name'] ?? null,
                    ':supplier_id' => $line['supplier']['id'] ?? null,
                    ':supplier_name' => $line['supplier']['name'] ?? null,
                    ':supplier_number' => $line['supplier']['number'] ?? null,
                    ':uom_code' => $line['uom']['code'] ?? null,
                    ':account_id' => $line['account']['id'] ?? null,
                    ':account_name' => $line['account']['name'] ?? null,
                    ':account_code' => $line['account']['code'] ?? null,
                    ':created_at' => isset($line['created-at']) ? date('Y-m-d H:i:s', strtotime($line['created-at'])) : null
                ]);
            }
        }
        
        // Insert approvals
        if (!empty($data['approvals'])) {
            $approvalSql = "INSERT INTO inbound_approvals (
                header_id, approval_id, position, approval_chain_id, status,
                approval_date, note, type, approvable_type, approvable_id,
                parallel_group_name, delegate_id, approved_by_id, approved_by_login,
                approved_by_email, created_at, updated_at
            ) VALUES (
                :header_id, :approval_id, :position, :approval_chain_id, :status,
                :approval_date, :note, :type, :approvable_type, :approvable_id,
                :parallel_group_name, :delegate_id, :approved_by_id, :approved_by_login,
                :approved_by_email, :created_at, :updated_at
            )";
            
            $approvalStmt = $pdo->prepare($approvalSql);
            
            foreach ($data['approvals'] as $approval) {
                $approvalStmt->execute([
                    ':header_id' => $headerId,
                    ':approval_id' => $approval['id'] ?? null,
                    ':position' => $approval['position'] ?? null,
                    ':approval_chain_id' => $approval['approval-chain-id'] ?? null,
                    ':status' => $approval['status'] ?? null,
                    ':approval_date' => isset($approval['approval-date']) ? date('Y-m-d H:i:s', strtotime($approval['approval-date'])) : null,
                    ':note' => $approval['note'] ?? null,
                    ':type' => $approval['type'] ?? null,
                    ':approvable_type' => $approval['approvable-type'] ?? null,
                    ':approvable_id' => $approval['approvable-id'] ?? null,
                    ':parallel_group_name' => $approval['parallel-group-name'] ?? null,
                    ':delegate_id' => $approval['delegate-id'] ?? null,
                    ':approved_by_id' => $approval['approved-by']['id'] ?? null,
                    ':approved_by_login' => $approval['approved-by']['login'] ?? null,
                    ':approved_by_email' => $approval['approved-by']['email'] ?? null,
                    ':created_at' => isset($approval['created-at']) ? date('Y-m-d H:i:s', strtotime($approval['created-at'])) : null,
                    ':updated_at' => isset($approval['updated-at']) ? date('Y-m-d H:i:s', strtotime($approval['updated-at'])) : null
                ]);
            }
        }
        
        $pdo->commit();
        
        // Call SAP RFC ZKPN_TEST after successful database save
        if ($headerId && !empty($data['requisition-lines'])) {
            $rfcData = [];
            $requestedById = $data['requested-by']['id'] ?? null;
            
            foreach ($data['requisition-lines'] as $line) {
                $rfcData[] = [
                    'requested_by_id' => $requestedById,
                    'line_id' => $line['id'] ?? null
                ];
            }
            
            // Call RFC and record history (non-blocking - errors won't affect response)
            try {
                $rfcResult = callZkpnTest($rfcData, $headerId);
                if (!$rfcResult['success']) {
                    error_log("RFC ZKPN_TEST call failed for header $headerId: " . ($rfcResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                error_log("RFC ZKPN_TEST exception for header $headerId: " . $e->getMessage());
            }
        }
        
        return $headerId;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database save error: " . $e->getMessage());
        return false;
    }
}

// Pastikan folder inbound ada
if (!file_exists(INBOUND_DIR)) {
    mkdir(INBOUND_DIR, 0755, true);
}
