<?php

// Test voting interface

require(__DIR__ . '/../../../../src/XF.php');
\XF::start(__DIR__ . '/../../../../');
$app = \XF::setupApp('XF\Pub\App');

$pollId = 2;

echo "Testing Ranked Poll Voting Interface\n";
echo str_repeat('=', 50) . "\n\n";

// Get the poll
$poll = \XF::em()->find('XF:Poll', $pollId);

if (!$poll)
{
	echo "ERROR: Poll #{$pollId} not found!\n";
	exit(1);
}

echo "Poll: {$poll->question}\n";
echo "Poll type: {$poll->poll_type}\n";
echo "Is ranked: " . ($poll->isRankedPoll() ? 'YES' : 'NO') . "\n\n";

// Check if route exists
echo "Checking route registration:\n";
$router = \XF::app()->router('public');
try {
	$link = $router->buildLink('polls/ranked-vote', $poll);
	echo "✓ Route works: {$link}\n";
} catch (\Exception $e) {
	echo "✗ Route failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Check controller
echo "Checking controller:\n";
try {
	$controller = \XF::app()->controller('Alebarda\RankedPoll:Vote', \XF::app()->request());
	echo "✓ Controller class: " . get_class($controller) . "\n";
} catch (\Exception $e) {
	echo "✗ Controller failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Check template
echo "Checking template:\n";
$template = \XF::app()->templater()->getTemplate('poll_block_ranked');
if ($template) {
	echo "✓ Template poll_block_ranked exists\n";
	// Check if it has the correct action
	if (strpos($template, "polls/ranked-vote") !== false) {
		echo "✓ Template uses correct route (polls/ranked-vote)\n";
	} else {
		echo "✗ Template does NOT use polls/ranked-vote route\n";
	}
} else {
	echo "✗ Template poll_block_ranked not found\n";
}

echo "\n";

// Check poll responses
echo "Poll responses:\n";
foreach ($poll->Responses as $response)
{
	echo "  [{$response->poll_response_id}] {$response->response}\n";
}

echo "\n";
echo "All checks completed!\n";
echo "\nNext step: Visit the poll in browser and try voting:\n";
echo "URL: https://beta.politsim.ru/index.php?polls/{$pollId}/\n";
