# Cloudflare Tunnel (simas.biz.id)

Aplikasi lokal dibuka lewat tunnel `simas-local-dimz` ke `http://localhost:8000`.

## Hostname dan DNS

- `simas.biz.id` (domain pusat) dan `console.simas.biz.id` (konsol provider).
- Setiap hostname butuh rule di tunnel dan record DNS CNAME (proxied) ke `<tunnel-id>.cfargotunnel.com`. Rule saja tidak cukup.
- Subdomain sekolah (`slug.simas.biz.id`) butuh rule `*.simas.biz.id` dan CNAME `*`.

## `.env`

```
APP_URL=https://simas.biz.id
TENANCY_CONSOLE_DOMAIN=console.simas.biz.id
TENANCY_CENTRAL_DOMAINS=simas.biz.id,localhost,127.0.0.1
TENANCY_URL_SCHEME=https
```

Nilai bawaan (`console.localhost`, `localhost`) membuat konsol tidak bisa dibuka di domain asli. Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Aset frontend

Lewat tunnel pakai `npm run build`, bukan Vite dev. Selama dev server jalan, `public/hot` membuat halaman memuat aset dari `[::1]:5174` yang tidak terjangkau browser lain. Hentikan Vite lalu pastikan `public/hot` terhapus.

## Trusted proxy

`bootstrap/app.php` memakai `trustProxies(at: '*')`. Tanpa itu, redirect setelah login menjadi `http://...` dan diblokir browser sebagai mixed content. Di Hostinger, persempit ke IP proxy yang dipakai.

## Gejala dan penyebab

| Gejala | Penyebab |
|---|---|
| Konsol tidak bisa diakses | `TENANCY_CONSOLE_DOMAIN` / `TENANCY_CENTRAL_DOMAINS` belum diisi, atau DNS `console` belum ada |
| CSS/JS gagal dimuat dari `[::1]:5174` | `public/hot` masih ada |
| Login berhasil tapi redirect diblokir (mixed content) | Trusted proxy belum diatur |
