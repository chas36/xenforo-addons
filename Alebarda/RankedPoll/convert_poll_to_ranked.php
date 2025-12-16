<?php

/**
 * Simple script to convert a standard poll to ranked poll
 *
 * Usage: php convert_poll_to_ranked.php <poll_id>
 */

$dir = __DIR__ . '/../../../..';
require($dir . '/src/XF.php');
\XF::start($dir);
$app = \XF::setupApp('XF\Pub\App');

// Get poll ID from command line
$pollId = isset($argv[1]) ? (int)$argv[1] : 0;

if (!$pollId) {
    die("Usage: php convert_poll_to_ranked.php <poll_id>\n");
}

// Find poll
$poll = \XF::em()->find('XF:Poll', $pollId);

if (!$poll) {
    die("Poll #{$pollId} not found\n");
}

echo "Converting Poll #{$pollId}: {$poll->question}\n";
echo "Current type: {$poll->poll_type}\n";

if ($poll->poll_type === 'ranked') {
    die("Poll is already ranked!\n");
}

// Update poll type
\XF::db()->update('xf_poll', [
    'poll_type' => 'ranked',
    'ranked_results_visibility' => 'after_close'
], 'poll_id = ?', $pollId);

echo "\n✓ Successfully converted to ranked poll!\n";
echo "Ranked results visibility: after_close\n";
echo "\nPoll URL: " . \XF::app()->router('public')->buildLink('canonical:polls', $poll) . "\n";
