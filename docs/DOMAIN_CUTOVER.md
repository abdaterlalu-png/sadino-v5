# Cutover domain `sadino.dndjava.com`

## Gate 1 — DNS

Buat A record:

- Type: A
- Name: sadino
- Target: 187.77.114.198
- TTL: default

Jika memakai Cloudflare, gunakan DNS-only selama penerbitan sertifikat pertama.

## Gate 2 — Proxy Hostinger harus valid

SADINO V5 tidak lagi menebak network, entrypoint, atau certificate resolver. Sebelum memakai domain overlay, pastikan project Traefik Hostinger yang resmi/valid memang tersedia dan memiliki:

- external shared network;
- entrypoint HTTPS;
- certificate resolver yang benar-benar terdaftar;
- Docker provider yang membaca project lain.

Catat nilainya dari YAML project Traefik di Docker Manager. Jangan mengambil nilai dari container HIDEA yang sebelumnya terbukti memakai resolver tidak tersedia.

## Gate 3 — Aktifkan overlay

Gunakan `docker-compose.yml` bersama `docker-compose.domain.example.yml`, kemudian isi:

- `PUBLIC_DOMAIN=sadino.dndjava.com`
- `TRAEFIK_NETWORK=<network valid>`
- `TRAEFIK_ENTRYPOINT=<entrypoint 443 valid>`
- `TRAEFIK_CERTRESOLVER=<resolver valid>`
- `APP_URL=https://sadino.dndjava.com`
- `TRUST_PROXY=true`
- `SESSION_SECURE_COOKIE=true`

Redeploy project SADINO saja.

## Gate 4 — Verifikasi

- HTTPS trusted;
- SAN certificate mencakup `sadino.dndjava.com`;
- `/?page=login` menampilkan SADINO;
- tidak ada default Traefik 404;
- direct port dapat dikunci ke localhost setelah domain stabil.

Jika Gate 2 belum tersedia, jangan ulangi patch router otomatis. Gunakan direct URL sementara atau standardisasi Traefik Hostinger melalui maintenance terencana.
