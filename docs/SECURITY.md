# Security Baseline

- Secret hanya melalui environment variables.
- `.env` produksi tidak boleh masuk Git.
- Database tidak expose port host.
- Password Creator minimum 12 karakter dan wajib diganti.
- Session HttpOnly, SameSite Lax, dan Secure ketika HTTPS aktif.
- Login throttling aktif.
- CSRF aktif.
- Upload Excel divalidasi dan dibatasi.
- Creator critical action wajib audit dan safety guard.
