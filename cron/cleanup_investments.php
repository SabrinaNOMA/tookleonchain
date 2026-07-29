<?php
/**
 * cron/cleanup_investments.php
 * ------------------------------------------------
 * INTELLIGENT CLEANUP SCRIPT
 * ------------------------------------------------
 * Goal: Mark abandoned investment intents as 'canceled' without deleting data.
 * Logic: 
 * 1. Finds investments older than 48 hours.
 * 2. Must be in 'initiated' status.
 * 3. Must NOT have any associated payment record (even a failed one).
 * 4. Updates status to 'canceled'.
 */

// 1. Load Database Connection
// Adjust path depending on where you place this file. Assuming /cron/ folder.
$dbPath = __DIR__ . '/../src/db.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    // Fallback if running from root
    require_once __DIR__ . '/src/db.php'; 
}

if (!isset($pdo)) {
    die("❌ Error: Could not connect to database.");
}

try {
    echo "🔍 Starting Analysis of Abandoned Intents...\n";

    // 2. Define the Threshold (48 Hours)
    $hours = 48;
    $cutOffDate = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

    // 3. The "Smart" Query
    // We use a JOIN to ensure we only touch investments that have ZERO entries in the payments table.
    // This protects us from cancelling a transaction that might be 'pending' in the payments table.
    $sql = "
        UPDATE investments i
        LEFT JOIN payments p ON i.id = p.investment_id
        SET i.status = 'canceled',
            i.notes = CONCAT(COALESCE(i.notes, ''), '\n[System]: Auto-canceled due to inactivity (No Payment Detected).')
        WHERE i.status = 'initiated'
          AND i.created_at < :cutoff
          AND p.id IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cutoff' => $cutOffDate]);

    $count = $stmt->rowCount();

    // 4. Reporting
    if ($count > 0) {
        echo "✅ Cleanup Complete: Successfully canceled {$count} abandoned investment intents.\n";
        echo "   (Criteria: Older than {$hours} hours, no payment attempt found)\n";
    } else {
        echo "✨ Database is clean. No abandoned intents found older than {$hours} hours.\n";
    }

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}
?>