# KPN Validation Test — Next.js

Next.js implementation of the KPN Validation Test API. Built with TypeScript and PostgreSQL.

This is the Next.js counterpart of the original PHP version — both share the same database.


## Setup

**Requirements:** Node.js 18+, PostgreSQL

```bash
npm install
cp .env.example .env.local   # edit sesuai environment
npm run dev                   # development (port 3001)
```

Production:
```bash
npm run build
npm run start
```


## Environment Variables

Buat file `.env.local` berdasarkan `.env.example`:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=kpn_validation_test
DB_USER=postgres
DB_PASS=your_password

AUTH_USERNAME=yossy
AUTH_PASSWORD=yossy

SAP_ASHOST=your_sap_host
SAP_SYSNR=10
SAP_CLIENT=777
SAP_USER=your_sap_user
SAP_PASS=your_sap_pass

PORT=3001
```


## API Endpoints

### Inbound (POST, requires Basic Auth)

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| POST | `/api/inbound` | Simpan data inbound |
| GET | `/api/inbound` | Info API |

Auth: Basic Auth (credential dari env)

### Data Viewer (HTML)

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/data` | Tabel data utama |
| GET | `/data?items={id}` | Detail line items |
| GET | `/data?approvals={id}` | Approval chain |
| GET | `/data?file={filename}` | Download file |

### REST API (JSON, tanpa auth)

| Method | Endpoint | Keterangan |
|--------|----------|------------|
| GET | `/api/headers` | List semua header |
| GET | `/api/headers/{id}` | Detail header + items + approvals |
| GET | `/api/headers/{id}/items` | Items saja |
| GET | `/api/headers/{id}/approvals` | Approvals saja |


## Contoh Request

```bash
curl -X POST http://localhost:3001/api/inbound \
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


## Database

PostgreSQL database: `kpn_validation_test`

| Table | Keterangan |
|-------|------------|
| `inbound_headers` | Header records (requisition, address, total) |
| `inbound_items` | Line items (material, qty, supplier) |
| `inbound_approvals` | Approval chain |
| `rfc_call_history` | SAP RFC call log |


## Alur Proses (POST /api/inbound)

1. Validasi Basic Auth
2. Parse JSON body
3. Simpan ke file `inbound/` (JSON, XML, CSV)
4. Insert ke PostgreSQL (headers → items → approvals)
5. Log RFC history (ZKPN_TEST)
6. Return response JSON


## Struktur Folder

```
app/
  layout.tsx
  page.tsx
  globals.css
  data/route.ts             → HTML data viewer
  api/
    inbound/route.ts        → POST handler
    headers/
      route.ts              → GET list
      [id]/
        route.ts            → GET detail
        items/route.ts      → GET items
        approvals/route.ts  → GET approvals
lib/
  db.ts                     → PostgreSQL pool
  auth.ts                   → Basic Auth
  save-to-db.ts             → Insert logic
  utils.ts                  → XML/CSV helpers
inbound/                    → File storage
```


## Perbedaan dengan Versi PHP

| | PHP | Next.js |
|-|-----|---------|
| POST endpoint | `POST /` | `POST /api/inbound` |
| Runtime | PHP 8.x | Node.js + Next.js 16 |
| Port | 8080 | 3001 |
| SAP RFC | SAPNWRFC ext | Log only |
| Database | sama | sama |


## Tech Stack

- Next.js 16 (App Router)
- TypeScript
- Tailwind CSS
- PostgreSQL (pg driver)
- fast-xml-parser


---

Wahyu Amaldi — Technical Lead, KPMG
