<?php

/**
 * Script to convert a standard poll to ranked poll
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
echo "Current type: " . ($poll->exists('poll_type') ? $poll->poll_type : 'standard (column not exists)') . "\n";

// Update poll type via direct SQL (safest method)
\XF::db()->update('xf_poll', [
    'poll_type' => 'ranked'
], 'poll_id = ?', $pollId);

// Create metadata record
\XF::db()->insert('xf_alebarda_ranked_poll_metadata', [
    'poll_id' => $pollId,
    'is_ranked' => 1,
    'results_visibility' => 'realtime',
    'allowed_user_groups' => null,
    'open_date' => null,
    'close_date' => $poll->close_date,
    'show_voter_list' => 1
], false, 'poll_id = VALUES(poll_id)');

echo "\n✅ Successfully converted to ranked poll!\n";
echo "Results visibility: realtime\n";
echo "Show voter list: yes\n";
echo "\nPoll URL: " . \XF::app()->router('public')->buildLink('canonical:polls', $poll) . "\n";
