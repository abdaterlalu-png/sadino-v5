# Urutan deployment produksi

1. Backup V4 dan verifikasi checksum.
2. Upload V5 ke private repository.
3. Deploy V5 sebagai project `sadino-v5` melalui Compose from URL.
4. Gunakan volume eksplisit existing; jangan hapus volume.
5. Uji Direct URL sampai seluruh UAT PASS.
6. Buat DNS A `sadino` menuju VPS.
7. Validasi shared Traefik resmi Hostinger.
8. Aktifkan domain overlay hanya jika network, entrypoint, dan resolver valid.
9. Uji HTTPS trusted dan login.
10. Setelah stabil, batasi direct port ke localhost.
