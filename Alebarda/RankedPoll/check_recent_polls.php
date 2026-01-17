<?php

require(__DIR__ . '/src/XF.php');
XF::start(__DIR__);

$db = XF::db();

echo "=== Recent Polls ===\n";
$polls = $db->fetchAll("
    SELECT poll_id, question, poll_type, UNIX_TIMESTAMP() - poll_id as approx_seconds
    FROM xf_poll
    ORDER BY poll_id DESC
    LIMIT 5
");

foreach ($polls as $p) {
    $type = $p['poll_type'] ?? 'NULL';
    $typeEmoji = $type === 'ranked' ? '✅' : '❌';

    echo "Poll #{$p['poll_id']}: {$p['question']}\n";
    echo "  Type: {$typeEmoji} {$type}\n\n";
}

// Check if debug log exists
$logFile = __DIR__ . '/internal_data/ranked_poll_debug.log';
if (file_exists($logFile)) {
    echo "\n=== Debug Log (last 30 lines) ===\n";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -30);
    echo implode('', $lastLines);
} else {
    echo "\n=== Debug log not found yet ===\n";
}
