# SADINO V5.0.0 — START HERE

Versi ini menghentikan seluruh pola V4 berupa BAT, SCP, SSH patching, cloning router, dan modifikasi shared Traefik secara otomatis.

## Jalur deploy yang didukung

1. Upload folder ini ke repository GitHub/GitLab.
2. Di Hostinger: VPS → Manage → Docker Manager → Compose → Compose from URL.
3. Masukkan URL langsung ke `docker-compose.yml` dari repository.
4. Nama proyek: `sadino-v5`.
5. Salin `.env.example` menjadi `.env` di repository/private deployment context dan isi secret kuat.
6. Deploy.
7. Validasi terlebih dahulu melalui `http://IP-VPS:8095/?page=login`.
8. Baru lakukan cutover domain `sadino.dndjava.com` sesuai `docs/DOMAIN_CUTOVER.md`.

Jangan mengaktifkan `docker-compose.domain.example.yml` sebelum proxy Hostinger yang benar telah diverifikasi.
