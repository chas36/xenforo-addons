<?php

require(__DIR__ . '/src/XF.php');
XF::start(__DIR__);

$app = XF::app();
$db = $app->db();

// Check code event listeners
echo "=== Code Event Listeners ===\n";
$listeners = $db->fetchAll("
    SELECT event_id, callback_class, callback_method, active, hint
    FROM xf_code_event_listener
    WHERE addon_id = 'Alebarda/RankedPoll'
    ORDER BY event_id
");

foreach ($listeners as $listener) {
    $status = $listener['active'] ? '✅' : '❌';
    echo "{$status} {$listener['event_id']}\n";
    echo "   Class: {$listener['callback_class']}\n";
    echo "   Method: {$listener['callback_method']}\n";
    echo "   Hint: {$listener['hint']}\n";
    echo "   Active: " . ($listener['active'] ? 'YES' : 'NO') . "\n\n";
}

echo "\n=== Check Listener.php exists ===\n";
$listenerPath = __DIR__ . '/src/addons/Alebarda/RankedPoll/Listener.php';
if (file_exists($listenerPath)) {
    echo "✅ Listener.php exists\n";

    // Check if methods exist
    require_once($listenerPath);
    $reflection = new ReflectionClass('Alebarda\RankedPoll\Listener');

    $methods = ['pollEntityPreSave', 'pollEntityPostSave', 'templaterTemplatePreRender'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "✅ Method {$method} exists\n";
        } else {
            echo "❌ Method {$method} NOT FOUND\n";
        }
    }
} else {
    echo "❌ Listener.php NOT FOUND at: {$listenerPath}\n";
}

// Check class extensions
echo "\n=== Class Extensions ===\n";
$extensions = $db->fetchAll("
    SELECT extension_id, from_class, to_class, active
    FROM xf_class_extension
    WHERE addon_id = 'Alebarda/RankedPoll'
    ORDER BY from_class
");

foreach ($extensions as $ext) {
    $status = $ext['active'] ? '✅' : '❌';
    echo "{$status} {$ext['from_class']}\n";
    echo "   → {$ext['to_class']}\n";
    echo "   Active: " . ($ext['active'] ? 'YES' : 'NO') . "\n\n";
}

// Check if poll_type column exists
echo "\n=== Database Check ===\n";
$columns = $db->fetchAllColumn("SHOW COLUMNS FROM xf_poll LIKE 'poll_type'");
if (!empty($columns)) {
    echo "✅ Column xf_poll.poll_type exists\n";
} else {
    echo "❌ Column xf_poll.poll_type NOT FOUND\n";
}

// Check recent polls
echo "\n=== Recent Polls ===\n";
$recentPolls = $db->fetchAll("
    SELECT poll_id, question, poll_type, voter_count
    FROM xf_poll
    ORDER BY poll_id DESC
    LIMIT 5
");

foreach ($recentPolls as $poll) {
    $type = $poll['poll_type'] ?? 'NULL';
    echo "Poll #{$poll['poll_id']}: {$poll['question']}\n";
    echo "   Type: {$type}\n";
    echo "   Voters: {$poll['voter_count']}\n\n";
}
