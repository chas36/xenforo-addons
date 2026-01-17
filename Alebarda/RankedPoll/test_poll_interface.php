<?php

// Test script to verify ranked poll interface

require(__DIR__ . '/../../../../src/XF.php');
\XF::start(__DIR__ . '/../../../../');
$app = \XF::setupApp('XF\Pub\App');

$pollId = 2;

echo "Testing Ranked Poll Interface for Poll ID: {$pollId}\n";
echo str_repeat('=', 50) . "\n\n";

// Get the poll
$poll = \XF::em()->find('XF:Poll', $pollId);

if (!$poll)
{
	echo "ERROR: Poll #{$pollId} not found!\n";
	exit(1);
}

echo "Poll found: {$poll->question}\n";
echo "Poll type: {$poll->poll_type}\n";
echo "Is ranked poll: " . ($poll->isRankedPoll() ? 'YES' : 'NO') . "\n";
echo "Number of responses: " . count($poll->Responses) . "\n\n";

echo "Poll responses:\n";
foreach ($poll->Responses as $response)
{
	echo "  - [{$response->poll_response_id}] {$response->response}\n";
}

echo "\n";

// Check if template modification is active
$templateMods = \XF::finder('XF:TemplateModification')
	->where('addon_id', 'Alebarda/RankedPoll')
	->where('template', 'poll_block')
	->fetch();

echo "\nTemplate modifications:\n";
foreach ($templateMods as $mod)
{
	echo "  - {$mod->description} (enabled: " . ($mod->enabled ? 'YES' : 'NO') . ")\n";
}

echo "\n";
echo "Test completed successfully!\n";
