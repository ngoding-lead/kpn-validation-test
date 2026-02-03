# Setup Cloudflare Tunnel untuk kpn-validation-test.ilmuprogram.app

## Prasyarat
- Akun Cloudflare dengan domain ilmuprogram.app sudah terdaftar
- PHP sudah terinstall di server
- cloudflared sudah terinstall

## Langkah 1: Install cloudflared (jika belum)

```bash
# Untuk Debian/Ubuntu
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb

# Atau menggunakan apt
sudo apt install cloudflared
```

## Langkah 2: Login ke Cloudflare

```bash
cloudflared tunnel login
```

Ini akan membuka browser untuk autentikasi dengan akun Cloudflare Anda.

## Langkah 3: Buat Tunnel

```bash
cloudflared tunnel create kpn-validation-test
```

Catat Tunnel ID yang dihasilkan.

## Langkah 4: Konfigurasi DNS

```bash
cloudflared tunnel route dns kpn-validation-test kpn-validation-test.ilmuprogram.app
```

## Langkah 5: Buat Config File

Buat file `~/.cloudflared/config.yml`:

```yaml
tunnel: <TUNNEL_ID>
credentials-file: /home/<username>/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: kpn-validation-test.ilmuprogram.app
    service: http://localhost:8080
  - service: http_status:404
```

## Langkah 6: Jalankan PHP Built-in Server

```bash
cd /home/clientsystem/Documents/Github/kpn-validation-test
php -S localhost:8080
```

## Langkah 7: Jalankan Tunnel

```bash
cloudflared tunnel run kpn-validation-test
```

## Langkah 8: (Opsional) Jalankan sebagai Service

```bash
sudo cloudflared service install
sudo systemctl start cloudflared
sudo systemctl enable cloudflared
```

---

## Testing API

### Menggunakan cURL

```bash
curl -X POST https://kpn-validation-test.ilmuprogram.app \
  -H "Authorization: Bearer kpn-validation-secret-token-2026" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com", "phone": "08123456789"}'
```

### Response Sukses

```json
{
  "success": true,
  "message": "Data saved successfully.",
  "filename": "inbound_2026-02-03_10-30-45_abc123.csv",
  "timestamp": "2026-02-03 10:30:45",
  "data_received": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08123456789"
  }
}
```

### Response Error (Token Invalid)

```json
{
  "success": false,
  "message": "Invalid token.",
  "timestamp": "2026-02-03 10:30:45"
}
```

---

## Struktur File CSV

File CSV yang disimpan di folder `inbound/` akan memiliki format:

| _metadata_received_at | _metadata_remote_ip | _metadata_user_agent | _metadata_content_type | name | email | phone |
|----------------------|--------------------|--------------------|----------------------|------|-------|-------|
| 2026-02-03 10:30:45 | 127.0.0.1 | curl/7.68.0 | application/json | John Doe | john@example.com | 08123456789 |
