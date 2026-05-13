<?php
require_once __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Permission-based Page Guard
|--------------------------------------------------------------------------
| Each page may define:
|     $REQUIRE_PERMISSION = 'permission_name';
|
| The page_permissions table (migration 023) lets admins override which
| permission is required for any page.  DB value takes precedence over
| the PHP constant when both are present.
|--------------------------------------------------------------------------
*/

if (isset($REQUIRE_PERMISSION)) {
    // Look for an admin-configured override in the page_permissions table.
    // Fall back to the hard-coded constant if the table doesn't exist yet
    // or if no row is found for the current page.
    $effectivePermission = $REQUIRE_PERMISSION;

    try {
        $pagePath = strtok($_SERVER['REQUEST_URI'], '?');  // strip query string
        $ppStmt = $pdo->prepare("
            SELECT permission_name
            FROM page_permissions
            WHERE page_path = ? AND is_active = 1
            LIMIT 1
        ");
        $ppStmt->execute([$pagePath]);
        $dbPerm = $ppStmt->fetchColumn();
        if ($dbPerm !== false && $dbPerm !== '') {
            $effectivePermission = $dbPerm;
        }
    } catch (Exception $e) {
        // Table may not exist yet – silently fall back to hard-coded value.
    }

    require_permission($effectivePermission);
}
