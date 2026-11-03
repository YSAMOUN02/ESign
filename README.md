# ESign — Laravel + MySQL e-signature system

A self-hosted document signing app (similar in concept to DocuSign or HelloSign), built with **Laravel 12**, **MySQL** and Blade. It has no Node build step.

## Features

- **Accounts**: register, log in, log out.
- **Upload PDFs**, then add signers and place fields on the pages with drag & drop. Field types: signature, initials, full name, date signed, and text.
- **Sending**: each signer gets an e-mail with a private signing link that is unique to them.
- **Signing order**: signers can sign at the same time, or one after another. In order mode, the next signer is e-mailed only after the previous one has signed.
- **Signing page**: signers can draw a signature (mouse or touch) or type one, fill in their fields and give consent. They can also decline.
- **Final PDF**: when everyone has signed, all signatures and values are stamped into the PDF (FPDI). A **Certificate of Completion** page is added with signer IPs, timestamps, the full audit trail and the SHA-256 hash of the original file.
- **Completion e-mail**: the signed PDF is attached to an e-mail sent to the sender and to every signer.
- **Dashboard**: status filters, signing progress, reminders, copy signing link, void, delete.
- **Audit log**: records every event (created, sent, e-mailed, viewed, signed, declined, voided, completed) with IP address and user agent.
- **Security**: original files and signature images are kept in private storage. Policies stop users from accessing documents they don't own. Signing links are rate-limited.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`
- Composer
- MySQL 8+ or MariaDB 10.6+

## Setup

```bash
git clone <repo> esign && cd esign
composer install
cp .env.example .env
php artisan key:generate
```

Create the database:

```sql
CREATE DATABASE esign CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set `DB_*` in `.env`, then:

```bash
php artisan migrate
php artisan serve
```

Open http://127.0.0.1:8000, register, and upload a PDF.

### E-mail

By default `MAIL_MAILER=log`, so e-mails (including signing links) are written to `storage/logs/laravel.log`. For real delivery, configure SMTP in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, …). Signers can also be sent their link from the document page with **Copy link**.

### PDF compatibility

Stamping uses the free FPDI parser, which cannot read some PDFs saved with compressed cross-reference streams (PDF 1.5+). If an upload is rejected:

- re-save the file as PDF 1.4 (e.g. *Print → Save as PDF*), or
- install Ghostscript and set `GHOSTSCRIPT_PATH=/usr/bin/gs`. The app then converts such files automatically when they are uploaded.

## How it works

| Table | Purpose |
|---|---|
| `documents` | Uploaded PDF, status (`draft → sent → completed / declined / voided`), file hashes |
| `signers` | Name, e-mail, order, secret token, status, IP and time of signing |
| `document_fields` | Field type, page, position and size as fractions of the page, filled value |
| `audit_logs` | Every event with IP and user agent |

Main code:

- `app/Http/Controllers/DocumentController.php`: sender side (upload, prepare, send, remind, void, download)
- `app/Http/Controllers/SigningController.php`: public signing pages, accessed by token
- `app/Services/SigningWorkflow.php`: send, notify, sign, decline, complete
- `app/Services/PdfService.php`: PDF parsing, stamping, certificate page
- `public/js/editor.js`, `public/js/signing.js`: field editor and signing UI (pdf.js is bundled in `public/vendor/pdfjs`)

## Tests

```bash
php artisan test
```

Tests use in-memory SQLite by default. To run them against MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=esign_test php artisan test
```

## Production notes

- Use `QUEUE_CONNECTION=database` (or redis) and make the mailables queued if you send a lot of e-mail.
- Serve over HTTPS. Signing links are bearer tokens.
- Back up `storage/app/private`. It holds the original PDFs, signatures and signed PDFs.
