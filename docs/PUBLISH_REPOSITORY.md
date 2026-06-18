# Publish Repository untuk Compose from URL

## Public repository

Hostinger dapat menerima URL langsung ke `docker-compose.yml`. Pastikan Dockerfile dan seluruh build context tersedia di repository yang sama.

## Private GitHub repository

Gunakan deploy key khusus repository sesuai dokumentasi Hostinger. Jangan memasukkan password root, `.env`, SSH private key, atau database backup ke repository.

## Environment variables

Masukkan secret melalui pengaturan project Docker Manager. Jangan commit `.env` produksi.
