# Modern File Manager

Modern File Manager is an admin-only WordPress plugin that provides a single-page file manager with no full page refresh while navigating folders.

## Features (v1)

- Interactive folder navigation (SPA in wp-admin)
- List files and folders
- Create folder and file
- Rename, move, copy, delete
- Upload and download
- In-app code editor (CodeMirror 6) with syntax highlight and fold gutter
- Breadcrumb navigation, search, sorting, detail panel
- Keyboard shortcuts:
  - `Ctrl/Cmd + R`: refresh current folder
  - `Delete`: delete selected
  - `F2`: rename selected item

## Security Model

- Admin capability required: `manage_options`
- REST route permission callback on every endpoint
- Nonce validation (`wp_rest`)
- Sandbox root restricted to `ABSPATH`
- Path traversal blocked
- Denylist for sensitive paths (`/wp-config.php`, `/.htaccess`, `/.git`, `/.env`)

## REST Endpoints

Base: `/wp-json/modern-file-manager/v1`

- `GET /list?path=/...`
- `POST /mkdir`
- `POST /create-file`
- `POST /rename`
- `POST /move`
- `POST /copy`
- `POST /delete`
- `POST /upload`
- `GET /download?path=/...`
- `GET /read-file?path=/...`
- `POST /save-file`

Success response:

```json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

Error response:

```json
{
  "ok": false,
  "code": "invalid_path",
  "message": "Target path is not a directory.",
  "details": {
    "status": 400
  }
}
```

## Testing

### 1) Backend security and filesystem tests (PHPUnit)

Install:

```bash
composer install
```

Run:

```bash
composer test
```

Covers:

- Root listing behavior
- Denylist protection
- Traversal rejection
- Create/rename/move/delete flow
- Root-delete prevention

### 2) UI smoke test (Playwright)

Install:

```bash
npm install
npx playwright install
```

Set env vars and run:

```bash
MFM_BASE_URL="http://dev.localhost" \
MFM_ADMIN_USER="admin" \
MFM_ADMIN_PASS="your-password" \
npm run test:e2e
```

Notes:

- The test is skipped automatically when `MFM_ADMIN_PASS` is not provided.
- It verifies the file manager page loads and navigation stays in-app (no full reload route change).

## Notes

- The plugin currently uses direct filesystem functions with strict policy checks.
- CodeMirror 6 is bundled locally in `assets/js/codemirror-bundle.js` (no CDN runtime dependency).
- For environments requiring alternate filesystem transport (FTP/SSH), extend `Filesystem_Service` to integrate `WP_Filesystem` transport selection.
