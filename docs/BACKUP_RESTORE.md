# Backup dan Restore

## Backup

Jalankan `scripts/backup_current_sadino.sh` pada VPS. Script hanya membaca container project `sadino` dan menghasilkan SQL gzip serta salinan upload.

## Restore

1. Stop app SADINO, biarkan database berjalan.
2. Verifikasi file checksum backup.
3. Import SQL ke database SADINO.
4. Pulihkan upload ke named volume.
5. Start app dan jalankan UAT.

Restore produksi harus dilakukan setelah snapshot VPS tersedia.
