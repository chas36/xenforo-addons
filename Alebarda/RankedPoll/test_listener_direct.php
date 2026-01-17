<?php

require(__DIR__ . '/src/XF.php');
XF::start(__DIR__);

echo "=== Testing Listener Direct Call ===\n\n";

// Create a mock poll entity
$em = \XF::em();
$poll = $em->create('XF:Poll');

echo "1. Created empty poll entity\n";
echo "   poll_type before: " . ($poll->poll_type ?? 'NULL') . "\n\n";

// Simulate request data
$_POST['poll'] = [
    'enable_ranked_voting' => '1',
    'question' => 'Test question'
];

echo "2. Set POST data: poll[enable_ranked_voting] = 1\n\n";

// Call the listener directly
echo "3. Calling Listener::pollEntityPreSave()...\n";
try {
    \Alebarda\RankedPoll\Listener::pollEntityPreSave($poll);
    echo "   ✅ Listener called successfully\n\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "4. poll_type after listener: " . ($poll->poll_type ?? 'NULL') . "\n\n";

// Check if debug log was created
$logFile = __DIR__ . '/internal_data/ranked_poll_debug.log';
if (file_exists($logFile)) {
    echo "5. ✅ Debug log created!\n";
    echo "   Last 10 lines:\n";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    foreach ($lastLines as $line) {
        echo "   " . $line;
    }
} else {
    echo "5. ❌ Debug log NOT created at: {$logFile}\n";
}
