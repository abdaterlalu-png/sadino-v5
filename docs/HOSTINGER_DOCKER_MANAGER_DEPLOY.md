# Deploy melalui Hostinger Docker Manager

## A. Persiapan repository

1. Buat repository GitHub/GitLab, disarankan private.
2. Upload seluruh isi folder SADINO V5 ke root repository.
3. Jangan commit `.env` produksi.
4. Untuk private GitHub, pasang deploy key sesuai panduan Hostinger.

## B. Deploy project

1. Buka hPanel → VPS → Manage.
2. Pilih Docker Manager.
3. Klik Compose → Compose from URL.
4. Masukkan direct URL ke `docker-compose.yml`.
5. Project name: `sadino-v5`.
6. Tambahkan environment variables dari `.env.example` dengan secret sebenarnya.
7. Klik Deploy.
8. Tunggu app dan db menjadi Running/Healthy.

## C. UAT awal

Buka `http://187.77.114.198:8095/?page=login`.

Uji login Creator, dashboard, upload template, audit, logout, dan restart container.

## D. Update

- Backup database terlebih dahulu.
- Update source/tag repository.
- Gunakan Update/Redeploy dari Docker Manager.
- Named volumes `sadino_db_data`, `sadino_storage`, dan `sadino_uploads` tetap dipertahankan.
