# 📦 KPN Validation Test — Next.js Version

> **Next.js 16 + TypeScript + PostgreSQL** implementation of the KPN Validation Test API.  
> Mirrors the original PHP version with identical functionality and shared database.

## 🌐 Live URL

| Version | URL |
|---------|-----|
| **Next.js** | [https://kpn-validation-test-nextjs.ilmuprogram.app](https://kpn-validation-test-nextjs.ilmuprogram.app) |
| PHP (original) | [https://kpn-validation-test.ilmuprogram.app](https://kpn-validation-test.ilmuprogram.app) |

---

## 🚀 Features

- **POST API** — Receive inbound data with Basic Auth, save to JSON/XML/CSV files + PostgreSQL
- **HTML Data Viewer** — Browse all records, items, and approval chains via HTML pages
- **JSON REST API** — Programmatic access to all data
- **File Storage** — Each submission saved as `.json`, `.xml`, and `.csv`
- **Database** — PostgreSQL with 4 tables (headers, items, approvals, rfc_history)
- **SAP RFC Logging** — RFC call history recorded (SAP NWRFC not available in Node.js)

---

## 📋 API Endpoints

### 🔐 Inbound (Requires Basic Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/inbound` | Save inbound data |
| `GET` | `/api/inbound` | API info & endpoints list |

**Authentication:** Basic Auth  
- Username: `yossy`  
- Password: `yossy`

### 📊 Data Viewer (HTML — No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/data` | Main data table (HTML) |
| `GET` | `/data?items={id}` | View line items for a header |
| `GET` | `/data?approvals={id}` | View approval chain |
| `GET` | `/data?file={filename}` | Download/view saved file |

### 🔌 JSON REST API (No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/headers` | List all inbound headers |
| `GET` | `/api/headers/{id}` | Get header with items, approvals, RFC history |
| `GET` | `/api/headers/{id}/items` | Get items for a header |
| `GET` | `/api/headers/{id}/approvals` | Get approvals for a header |

---

## 🧪 Test API Call

```bash
# POST inbound data
curl -X POST https://kpn-validation-test-nextjs.ilmuprogram.app/api/inbound \
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

# Get all headers (JSON API)
curl -s https://kpn-validation-test-nextjs.ilmuprogram.app/api/headers | python3 -m json.tool

# Get header detail
curl -s https://kpn-validation-test-nextjs.ilmuprogram.app/api/headers/1 | python3 -m json.tool
```

---

## 🗄️ Database

Uses the same PostgreSQL database as the PHP version: `kpn_validation_test`

### Tables

| Table | Description |
|-------|-------------|
| `inbound_headers` | Header records (requisition info, addresses, totals) |
| `inbound_items` | Line items (materials, quantities, suppliers) |
| `inbound_approvals` | Approval chain steps |
| `rfc_call_history` | SAP RFC call log |

### Connection

```
Host: localhost
Port: 5432
Database: kpn_validation_test
User: postgres
```

---

## 🔄 Flow Diagram

```
┌──────────────────────────────────────────────────────┐
│              POST /api/inbound                        │
│    https://kpn-validation-test-nextjs.ilmuprogram.app │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│              1. BASIC AUTH                            │
│           yossy / yossy                              │
│           ❌ 401/403 if failed                       │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│           2. PARSE JSON BODY                         │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│        3. SAVE TO FILES (inbound/)                   │
│    • JSON, XML, CSV                                  │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│        4. SAVE TO POSTGRESQL                         │
│    inbound_headers → inbound_items                   │
│                    → inbound_approvals               │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│        5. LOG RFC HISTORY                            │
│    (ZKPN_TEST recorded in rfc_call_history)          │
└──────────────────────────────────────────────────────┘
                        │
                        ▼
┌──────────────────────────────────────────────────────┐
│        6. RETURN JSON RESPONSE                       │
│    { success, database_id, files, timestamp }        │
└──────────────────────────────────────────────────────┘
```

---

## 📁 Project Structure

```
Next JS/
├── app/
│   ├── layout.tsx              # Root layout
│   ├── page.tsx                # Landing page (/)
│   ├── globals.css             # Tailwind CSS
│   ├── data/
│   │   └── route.ts            # GET /data — HTML data viewer
│   └── api/
│       ├── inbound/
│       │   └── route.ts        # POST /api/inbound — Save data
│       └── headers/
│           ├── route.ts        # GET /api/headers — List all
│           └── [id]/
│               ├── route.ts    # GET /api/headers/:id — Detail
│               ├── items/
│               │   └── route.ts    # GET /api/headers/:id/items
│               └── approvals/
│                   └── route.ts    # GET /api/headers/:id/approvals
├── lib/
│   ├── db.ts                   # PostgreSQL connection pool
│   ├── auth.ts                 # Basic Auth verification
│   ├── save-to-db.ts           # Database insert logic
│   └── utils.ts                # XML, CSV, date helpers
├── inbound/                    # Saved files directory
├── .env.local                  # Environment variables
├── next.config.ts              # Next.js config
├── package.json                # Dependencies & scripts
├── tsconfig.json               # TypeScript config
└── README.md                   # This file
```

---

## ⚙️ Setup & Run

### Prerequisites

- **Node.js** 18+ 
- **PostgreSQL** with `kpn_validation_test` database

### Install

```bash
cd "Next JS"
npm install
```

### Development

```bash
npm run dev
# → http://localhost:3001
```

### Production

```bash
npm run build
npm run start
# → http://localhost:3001
```

---

## 🔧 Configuration

Environment variables in `.env.local`:

```env
# PostgreSQL
DB_HOST=localhost
DB_PORT=5432
DB_NAME=kpn_validation_test
DB_USER=postgres
DB_PASS=********

# Basic Auth
AUTH_USERNAME=yossy
AUTH_PASSWORD=yossy

# SAP RFC (logged only)
SAP_ASHOST=192.168.1.103
SAP_SYSNR=10
SAP_CLIENT=777
SAP_USER=wahyu.amaldi

# Server
PORT=3001
```

---

## 🌍 Cloudflare Tunnel

| Setting | Value |
|---------|-------|
| Hostname | `kpn-validation-test-nextjs.ilmuprogram.app` |
| Service | `http://localhost:3001` |
| Tunnel | `client-management` (e0043a2e) |

---

## 📝 Differences from PHP Version

| Feature | PHP | Next.js |
|---------|-----|---------|
| **POST endpoint** | `POST /` | `POST /api/inbound` |
| **Runtime** | PHP 8.x + Apache/built-in server | Node.js + Next.js 16 |
| **Port** | 8080 | 3001 |
| **SAP RFC** | Real SAPNWRFC extension | Logged only (no Node.js NWRFC) |
| **Database** | Same (`kpn_validation_test`) | Same (`kpn_validation_test`) |
| **Data viewer** | Same HTML at `/data` | Same HTML at `/data` |
| **Styling** | Blue (#007bff) | Next.js Blue (#0070f3) |

---

## 📌 Tech Stack

- **Next.js 16** — React framework with App Router
- **TypeScript** — Type-safe code
- **Tailwind CSS** — Landing page styling
- **PostgreSQL** — via `pg` driver
- **fast-xml-parser** — JSON to XML conversion
- **Cloudflare Tunnel** — Public HTTPS access

---

## 👤 Author

| Field | Detail |
|-------|--------|
| **Developer** | Wahyu Amaldi |
| **Role** | Technical Lead |
| **Organization** | KPMG |
| **Project** | KPN Validation Test — Next.js Implementation |

---

*Last Updated: 2026-02-09*  
*Developed by Wahyu Amaldi — Technical Lead, KPMG*
