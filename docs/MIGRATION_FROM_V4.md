# Migrasi SADINO V4 ke V5

V5 memakai nama volume eksplisit yang sama:

- `sadino_db_data`
- `sadino_storage`
- `sadino_uploads`

Karena itu data dapat dipertahankan.

## Urutan aman

1. Buat backup menggunakan `scripts/backup_current_sadino.sh`.
2. Catat jumlah users, upload batches, audit logs, dan financial rows.
3. Stop hanya project lama `sadino` setelah backup valid.
4. Deploy project `sadino-v5`.
5. Jangan menghapus volume lama.
6. Validasi login, row count, upload, audit, dan Design Studio.
7. Jika gagal, stop `sadino-v5`, lalu start kembali project lama `sadino`.

Creator hanya dibuat jika belum ada Creator di database.
