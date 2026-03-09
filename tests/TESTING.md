# Manual Test Checklist

## Security

1. Access plugin as non-admin user and verify access is denied.
2. Call REST routes without nonce and verify `nonce_failed`.
3. Attempt path traversal (`../`) and verify blocked.
4. Attempt access to denylist path and verify blocked.

## Core Operations

1. Create folder and file in current path.
2. Rename file/folder.
3. Move item into another folder.
4. Copy item to another folder.
5. Delete item(s) and verify removal.
6. Upload a file and verify appears in list.
7. Download a file and verify browser download.

## UX and Interaction

1. Navigate folders and verify no full page reload.
2. Expand/collapse nested nodes in the left folder tree and verify lazy loading works.
3. Click a nested tree folder and verify main table updates to that path.
4. Right-click a file row and verify context menu shows edit/rename/move/copy/download/delete.
5. Right-click a folder row and verify context menu shows folder-appropriate actions.
6. Use breadcrumb navigation.
7. Search and sort in current folder.
8. Use shortcuts: `Delete`, `F2`, `Ctrl/Cmd + R`.
9. Verify responsive behavior in narrow viewport.
10. Verify focus ring appears when navigating via keyboard.

## Editor

1. Select one file and click `Edit` to open the editor modal.
2. Verify syntax highlighting appears for PHP/JS/CSS/HTML files.
3. Verify fold gutter markers appear and functions/blocks can be collapsed.
4. Edit content, click `Save`, and confirm file content changes on disk.
5. Close and reopen editor to verify persisted content.

## DB Manager

1. Open `wp-admin/admin.php?page=modern-file-manager-db` as admin and verify page loads.
2. Verify warning banner, environment badge text, and masked DB host/user metadata are visible.
3. Click `Open DB Manager` and confirm Adminer loads in the embedded frame.
4. Tamper launch URL token/signature and confirm safe error page (no credential leakage).
5. Disable `Enable DB Manager` setting, save, and confirm launch is blocked.
