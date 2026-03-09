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
2. Right-click a file row and verify context menu shows edit/rename/move/copy/download/delete.
3. Right-click a folder row and verify context menu shows folder-appropriate actions.
4. Use breadcrumb navigation.
5. Search and sort in current folder.
6. Use shortcuts: `Delete`, `F2`, `Ctrl/Cmd + R`.
7. Verify responsive behavior in narrow viewport.
8. Verify focus ring appears when navigating via keyboard.

## Editor

1. Select one file and click `Edit` to open the editor modal.
2. Verify syntax highlighting appears for PHP/JS/CSS/HTML files.
3. Verify fold gutter markers appear and functions/blocks can be collapsed.
4. Edit content, click `Save`, and confirm file content changes on disk.
5. Close and reopen editor to verify persisted content.
