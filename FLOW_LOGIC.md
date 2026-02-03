# KPN Validation Test - Flow Logic Documentation

## 🔄 Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API CALL (POST)                                 │
│                  https://kpn-validation-test.ilmuprogram.app                │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         1. AUTHENTICATION                                    │
│                    Basic Auth: yossy / yossy                                │
│                         ❌ 401 if failed                                     │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      2. PARSE JSON BODY                                      │
│                    Extract POST data from request                           │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    3. SAVE TO FILES (inbound/)                              │
│         • JSON: inbound_YYYY-MM-DD_HH-mm-ss_uniqueid.json                   │
│         • XML:  inbound_YYYY-MM-DD_HH-mm-ss_uniqueid.xml                    │
│         • CSV:  inbound_YYYY-MM-DD_HH-mm-ss_uniqueid.csv                    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                  4. SAVE TO POSTGRESQL DATABASE                             │
│                                                                              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐             │
│  │ inbound_headers │  │  inbound_items  │  │inbound_approvals│             │
│  │   (1 record)    │──│  (N records)    │  │   (N records)   │             │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘             │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                     5. CALL SAP RFC (ZKPN_TEST)                             │
│                                                                              │
│         Import Table: T_DATA                                                │
│         ┌─────────────────────┬─────────────────────┐                       │
│         │   REQUESTED_BY_ID   │       LINE_ID       │                       │
│         ├─────────────────────┼─────────────────────┤                       │
│         │        257          │        1264         │                       │
│         │        257          │        1265         │                       │
│         └─────────────────────┴─────────────────────┘                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                  6. RECORD RFC HISTORY                                       │
│                                                                              │
│         Table: rfc_call_history                                             │
│         • header_id, function_module, request_data                          │
│         • response_data, success, error_message                             │
│         • execution_time_ms, call_timestamp                                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      7. RETURN RESPONSE                                      │
│                                                                              │
│  {                                                                          │
│    "success": true,                                                         │
│    "message": "Data saved successfully.",                                   │
│    "database_id": 6,                                                        │
│    "files": { "json": "...", "xml": "...", "csv": "..." }                   │
│  }                                                                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📋 JSON to Table Field Mapping

### 1. JSON Input Structure (from Coupa)

```json
{
  "id": 450,
  "status": "pending_approval",
  "total": "13542.00",
  "currency": { "code": "IDR" },
  "requested-by": { "id": 257, "login": "Yossy" },
  "ship-to-address": { "id": 13670, "name": "...", "city": "..." },
  "requisition-lines": [
    { "id": 1264, "line-num": 1, "description": "...", ... }
  ],
  "approvals": [
    { "id": 7969, "position": 1, "status": "pending_approval", ... }
  ]
}
```

### 2. Table: `inbound_headers` (Header Data)

| JSON Field | Table Column | Type |
|------------|--------------|------|
| `id` | `requisition_id` | integer |
| `created-at` | `created_at` | timestamp |
| `updated-at` | `updated_at` | timestamp |
| `status` | `status` | varchar |
| `submitted-at` | `submitted_at` | timestamp |
| `ship-to-attention` | `ship_to_attention` | varchar |
| `exported` | `exported` | boolean |
| `total` | `total` | numeric |
| `total-with-estimated-tax` | `total_with_estimated_tax` | numeric |
| `estimated-tax-amount` | `estimated_tax_amount` | numeric |
| `rejected` | `rejected` | boolean |
| `currency.code` | `currency_code` | varchar |
| `requested-by.id` | `requested_by_id` | integer |
| `requested-by.login` | `requested_by_login` | varchar |
| `ship-to-address.id` | `ship_to_address_id` | integer |
| `ship-to-address.name` | `ship_to_address_name` | varchar |
| `ship-to-address.city` | `ship_to_address_city` | varchar |
| `ship-to-address.street1` | `ship_to_address_street1` | text |
| `buyer-note` | `buyer_note` | text |
| `justification` | `justification` | text |
| _(metadata)_ | `file_id`, `received_at`, `remote_ip`, `user_agent` | - |
| _(filenames)_ | `json_filename`, `xml_filename`, `csv_filename` | varchar |

### 3. Table: `inbound_items` (Line Items)

| JSON Field | Table Column | Type |
|------------|--------------|------|
| `requisition-lines[].id` | `line_id` | integer |
| `requisition-lines[].line-num` | `line_num` | integer |
| `requisition-lines[].description` | `description` | text |
| `requisition-lines[].quantity` | `quantity` | numeric |
| `requisition-lines[].total` | `total` | numeric |
| `requisition-lines[].source-part-num` | `source_part_num` | varchar |
| `requisition-lines[].status` | `status` | varchar |
| `requisition-lines[].item.id` | `item_id` | integer |
| `requisition-lines[].item.item-number` | `item_number` | varchar |
| `requisition-lines[].item.name` | `item_name` | varchar |
| `requisition-lines[].supplier.id` | `supplier_id` | integer |
| `requisition-lines[].supplier.name` | `supplier_name` | varchar |
| `requisition-lines[].supplier.number` | `supplier_number` | varchar |
| `requisition-lines[].uom.code` | `uom_code` | varchar |
| `requisition-lines[].account.id` | `account_id` | integer |
| `requisition-lines[].account.name` | `account_name` | varchar |
| `requisition-lines[].account.code` | `account_code` | varchar |
| `requisition-lines[].created-at` | `created_at` | timestamp |

### 4. Table: `inbound_approvals` (Approval Chain)

| JSON Field | Table Column | Type |
|------------|--------------|------|
| `approvals[].id` | `approval_id` | integer |
| `approvals[].position` | `position` | integer |
| `approvals[].approval-chain-id` | `approval_chain_id` | integer |
| `approvals[].status` | `status` | varchar |
| `approvals[].approval-date` | `approval_date` | timestamp |
| `approvals[].note` | `note` | text |
| `approvals[].type` | `type` | varchar |
| `approvals[].approvable-type` | `approvable_type` | varchar |
| `approvals[].approvable-id` | `approvable_id` | integer |
| `approvals[].parallel-group-name` | `parallel_group_name` | varchar |
| `approvals[].delegate-id` | `delegate_id` | integer |
| `approvals[].approved-by.id` | `approved_by_id` | integer |
| `approvals[].approved-by.login` | `approved_by_login` | varchar |
| `approvals[].approved-by.email` | `approved_by_email` | varchar |
| `approvals[].created-at` | `created_at` | timestamp |
| `approvals[].updated-at` | `updated_at` | timestamp |

### 5. Table: `rfc_call_history` (RFC Call Log)

| Column | Type | Description |
|--------|------|-------------|
| `id` | serial | Auto ID |
| `header_id` | integer | FK to inbound_headers |
| `function_module` | varchar | FM name (ZKPN_TEST) |
| `call_timestamp` | timestamp | When RFC was called |
| `request_data` | jsonb | Data sent to SAP |
| `response_data` | jsonb | Response from SAP |
| `success` | boolean | Success/failed |
| `error_message` | text | Error message if failed |
| `execution_time_ms` | integer | Execution time in ms |

---

## 🔗 SAP RFC Mapping

### Function Module: `ZKPN_TEST`

**Import Table: T_DATA**

| SAP Field | Source | Description |
|-----------|--------|-------------|
| `REQUESTED_BY_ID` | `requested-by.id` | Requester ID from Coupa |
| `LINE_ID` | `requisition-lines[].id` | Line ID from each item |

**Example Data Sent:**
```
T_DATA:
┌─────────────────────┬─────────────────────┐
│   REQUESTED_BY_ID   │       LINE_ID       │
├─────────────────────┼─────────────────────┤
│        257          │        1264         │
│        257          │        1265         │
│        257          │        1266         │
└─────────────────────┴─────────────────────┘
```

---

## 📊 Database Schema

```sql
-- Headers
CREATE TABLE inbound_headers (
    id SERIAL PRIMARY KEY,
    file_id VARCHAR(255),
    received_at TIMESTAMP,
    remote_ip VARCHAR(50),
    user_agent TEXT,
    requisition_id INTEGER,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    status VARCHAR(50),
    submitted_at TIMESTAMP,
    ship_to_attention VARCHAR(255),
    exported BOOLEAN DEFAULT FALSE,
    total NUMERIC(18,2),
    total_with_estimated_tax NUMERIC(18,2),
    estimated_tax_amount NUMERIC(18,2),
    rejected BOOLEAN DEFAULT FALSE,
    currency_code VARCHAR(10),
    requested_by_id INTEGER,
    requested_by_login VARCHAR(100),
    ship_to_address_id INTEGER,
    ship_to_address_name VARCHAR(255),
    ship_to_address_city VARCHAR(100),
    ship_to_address_street1 TEXT,
    buyer_note TEXT,
    justification TEXT,
    json_filename VARCHAR(255),
    xml_filename VARCHAR(255),
    csv_filename VARCHAR(255),
    db_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items
CREATE TABLE inbound_items (
    id SERIAL PRIMARY KEY,
    header_id INTEGER REFERENCES inbound_headers(id),
    line_id INTEGER,
    line_num INTEGER,
    description TEXT,
    quantity NUMERIC(18,4),
    total NUMERIC(18,2),
    source_part_num VARCHAR(100),
    status VARCHAR(50),
    item_id INTEGER,
    item_number VARCHAR(100),
    item_name VARCHAR(255),
    supplier_id INTEGER,
    supplier_name VARCHAR(255),
    supplier_number VARCHAR(50),
    uom_code VARCHAR(20),
    account_id INTEGER,
    account_name VARCHAR(255),
    account_code VARCHAR(50),
    created_at TIMESTAMP,
    db_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Approvals
CREATE TABLE inbound_approvals (
    id SERIAL PRIMARY KEY,
    header_id INTEGER REFERENCES inbound_headers(id),
    approval_id INTEGER,
    position INTEGER,
    approval_chain_id INTEGER,
    status VARCHAR(50),
    approval_date TIMESTAMP,
    note TEXT,
    type VARCHAR(100),
    approvable_type VARCHAR(100),
    approvable_id INTEGER,
    parallel_group_name VARCHAR(255),
    delegate_id INTEGER,
    approved_by_id INTEGER,
    approved_by_login VARCHAR(255),
    approved_by_email VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- RFC History
CREATE TABLE rfc_call_history (
    id SERIAL PRIMARY KEY,
    header_id INTEGER REFERENCES inbound_headers(id),
    function_module VARCHAR(100),
    call_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_data JSONB,
    response_data JSONB,
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    execution_time_ms INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🧪 Test API Call

```bash
curl -X POST https://kpn-validation-test.ilmuprogram.app \
  -u yossy:yossy \
  -H "Content-Type: application/json" \
  -d '{
    "id": 450,
    "status": "pending_approval",
    "total": "13542.00",
    "currency": {"code": "IDR"},
    "requested-by": {"id": 257, "login": "Yossy"},
    "ship-to-address": {
      "id": 13670,
      "name": "KOPERASI INDO PLASMA",
      "city": "Banyuasin"
    },
    "requisition-lines": [
      {
        "id": 1264,
        "line-num": 1,
        "description": "BIO-DIESEL (B30)",
        "quantity": "1.0",
        "total": "13542.00",
        "item": {"id": 27529, "item-number": "929.001.005"},
        "supplier": {"id": 13155, "name": "PT. ANPA MAJU"},
        "uom": {"code": "L"},
        "account": {"id": 5, "code": "EU"}
      }
    ],
    "approvals": [
      {"id": 7969, "position": 1, "status": "pending_approval", "approval-chain-id": 33}
    ]
  }'
```

---

## 📁 Files Structure

```
kpn-validation-test/
├── config.php          # Configuration & functions
├── index.php           # Main API handler
├── router.php          # PHP built-in server router
├── README.md           # API documentation
├── FLOW_LOGIC.md       # This file
└── inbound/            # Saved files
    ├── inbound_2026-02-03_22-31-25_xxx.json
    ├── inbound_2026-02-03_22-31-25_xxx.xml
    └── inbound_2026-02-03_22-31-25_xxx.csv
```

---

## ⚙️ Configuration

### PostgreSQL
```php
DB_HOST = 'localhost'
DB_PORT = '5432'
DB_NAME = 'kpn_validation_test'
DB_USER = 'postgres'
DB_PASS = 'Pas671_ok'
```

### SAP RFC
```php
SAP_ASHOST = '192.168.1.103'
SAP_SYSNR  = '10'
SAP_CLIENT = '777'
SAP_USER   = 'wahyu.amaldi'
SAP_PASS   = 'Pas671_ok12345'
```

### API Auth
```php
AUTH_USERNAME = 'yossy'
AUTH_PASSWORD = 'yossy'
```
