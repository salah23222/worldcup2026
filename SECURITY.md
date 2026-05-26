# Security Policy / سياسة الأمان

## Reporting a vulnerability / الإبلاغ عن ثغرة
Please **do not** open a public issue for security vulnerabilities.

- **Preferred:** open a private **GitHub Security Advisory**
  (repo → *Security* → *Report a vulnerability*).
- **Or email:** `security@example.com` *(replace with your address)*.

We aim to acknowledge reports within a few days. Please include reproduction steps,
affected files/endpoints, and the potential impact.

## Handling secrets / التعامل مع الأسرار
- All secrets live **only** in `includes/config.local.php` (git‑ignored) or environment variables.
- Never commit API keys, passwords, or tokens.
- If a key is exposed, **rotate it immediately** at the provider.
- Ensure your server returns **403** for `/includes/config.local.php`, `/data/`, and `/cache/`.
  On Nginx (which ignores `.htaccess`) add the equivalent deny rules in your server block.

## Built‑in protections / الحمايات المدمجة
Prepared statements (PDO), bcrypt password hashing, CSRF on state‑changing endpoints,
rate limiting, output escaping, and an SSRF guard on outbound fetches.

## Supported versions
The latest `main` branch receives security fixes.
