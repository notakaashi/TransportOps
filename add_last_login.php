<?php
/**
 * One-time migration: adds last_login column to users table
 * Run once via browser or CLI, then delete this file.
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Access denied. Admin login required.');
}

try {
    $pdo = getDBConnection();

    // Check if column already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'last_login'
    ");
    $stmt->execute();
    $exists = (int) $stmt->fetchColumn();

    if ($exists) {
        $msg  = '✅ Column <code>last_login</code> already exists — nothing to do.';
        $type = 'info';
    } else {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
        $msg  = '✅ Column <code>last_login</code> added to <code>users</code> table successfully.';
        $type = 'success';
    }
} catch (PDOException $e) {
    $msg  = '❌ Error: ' . htmlspecialchars($e->getMessage());
    $type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration – add last_login | TransportOps</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(34,51,92,0.10);
            padding: 2.5rem 2rem;
            max-width: 520px;
            width: 100%;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.75rem;
        }
        .brand-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #FBC061;
        }
        .brand-name {
            font-size: 1rem;
            font-weight: 700;
            color: #22335C;
            letter-spacing: 0.04em;
        }
        h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .sub {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        .result {
            padding: 1rem 1.1rem;
            border-radius: 0.6rem;
            font-size: 0.9rem;
            line-height: 1.55;
            margin-bottom: 1.5rem;
        }
        .result.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .result.info    { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .result.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .result code {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.88em;
            font-weight: 600;
            background: rgba(0,0,0,0.06);
            padding: 0.1em 0.35em;
            border-radius: 0.25em;
        }
        .divider { border: none; border-top: 1px solid #f1f5f9; margin: 1rem 0 1.25rem; }
        .note {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.55;
        }
        .note strong { color: #64748b; }
        .actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.1rem;
            border-radius: 0.5rem;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary {
            background: #22335C;
            color: #fff;
        }
        .btn-primary:hover { background: #1a2847; }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover { background: #e2e8f0; }
    </style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-dot"></div>
        <span class="brand-name">TransportOps</span>
    </div>

    <h1>Database Migration</h1>
    <p class="sub">
        Adds the <code style="font-family:monospace;font-size:0.88em;background:#f1f5f9;padding:0.1em 0.35em;border-radius:0.25em;color:#22335C;">last_login</code>
        column to the <code style="font-family:monospace;font-size:0.88em;background:#f1f5f9;padding:0.1em 0.35em;border-radius:0.25em;color:#22335C;">users</code>
        table — required for the "Welcome / Welcome back" greeting feature.
    </p>

    <div class="result <?php echo htmlspecialchars($type); ?>">
        <?php echo $msg; ?>
    </div>

    <hr class="divider">

    <p class="note">
        <strong>Next steps:</strong> This file is no longer needed once the migration runs
        successfully. You can safely delete <code style="font-family:monospace;font-size:0.88em;">add_last_login.php</code>
        from your server to prevent accidental re-runs.
    </p>

    <div class="actions">
        <a href="admin_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <a href="add_last_login.php" class="btn btn-secondary">Run Again</a>
    </div>
</div>
</body>
</html>
