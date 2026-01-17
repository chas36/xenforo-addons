<?php

require(__DIR__ . '/src/XF.php');
XF::start(__DIR__);

$app = XF::app();
$db = $app->db();

// Get template modification
echo "=== Template Modification ===\n";
$mods = $db->fetchAll("
    SELECT modification_id, template, description, enabled, find
    FROM xf_template_modification
    WHERE addon_id = 'Alebarda/RankedPoll'
");
print_r($mods);

echo "\n=== Searching for helper_poll_edit template ===\n";
// Try to find it in master templates
$masterTemplate = $db->fetchOne("
    SELECT template
    FROM xf_template
    WHERE title = 'helper_poll_edit'
    AND type = 'public'
    AND style_id = 0
");

if ($masterTemplate) {
    echo "Found in xf_template:\n";
    echo "Length: " . strlen($masterTemplate) . " chars\n\n";

    // Search for poll_options
    if (strpos($masterTemplate, 'poll_options') !== false) {
        echo "✅ Found 'poll_options' in template\n";

        // Find the specific line
        $lines = explode("\n", $masterTemplate);
        foreach ($lines as $num => $line) {
            if (strpos($line, 'poll_options') !== false) {
                echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
            }
        }
    } else {
        echo "❌ 'poll_options' NOT found in template\n";
    }

    echo "\n";

    // Search for checkboxrow
    if (strpos($masterTemplate, 'checkboxrow') !== false) {
        echo "✅ Found 'checkboxrow' in template\n";

        // Show surrounding context
        $lines = explode("\n", $masterTemplate);
        foreach ($lines as $num => $line) {
            if (strpos($line, 'checkboxrow') !== false && strpos($line, 'poll_options') !== false) {
                echo "\nContext around line " . ($num + 1) . ":\n";
                for ($i = max(0, $num - 2); $i <= min(count($lines) - 1, $num + 5); $i++) {
                    echo "  " . ($i + 1) . ": " . $lines[$i] . "\n";
                }
            }
        }
    } else {
        echo "❌ 'checkboxrow' NOT found in template\n";
    }
} else {
    echo "❌ Template 'helper_poll_edit' not found\n";
}

// List all poll-related templates
echo "\n=== All poll-related templates ===\n";
$pollTemplates = $db->fetchAll("
    SELECT title, type
    FROM xf_template
    WHERE title LIKE '%poll%'
    AND style_id = 0
    ORDER BY title
");
foreach ($pollTemplates as $t) {
    echo "- {$t['type']}:{$t['title']}\n";
}

// Check poll_macros template - FULL CONTENT
echo "\n=== poll_macros template FULL CONTENT ===\n";
$pollMacros = $db->fetchOne("
    SELECT template
    FROM xf_template
    WHERE title = 'poll_macros'
    AND type = 'public'
    AND style_id = 0
");

if ($pollMacros) {
    echo $pollMacros . "\n";
} else {
    echo "Not found\n";
}

// Check poll_edit template - show full content
echo "\n=== poll_edit template FULL CONTENT ===\n";
$pollEdit = $db->fetchOne("
    SELECT template
    FROM xf_template
    WHERE title = 'poll_edit'
    AND type = 'public'
    AND style_id = 0
");

if ($pollEdit) {
    echo $pollEdit . "\n";
} else {
    echo "Not found\n";
}

// Check thread_type_fields_poll
echo "\n=== thread_type_fields_poll template FULL CONTENT ===\n";
$threadTypeFields = $db->fetchOne("
    SELECT template
    FROM xf_template
    WHERE title = 'thread_type_fields_poll'
    AND type = 'public'
    AND style_id = 0
");

if ($threadTypeFields) {
    echo $threadTypeFields . "\n";

    // Look for checkboxrow
    if (strpos($threadTypeFields, 'checkboxrow') !== false) {
        echo "\n✅ Found 'checkboxrow' in thread_type_fields_poll\n";
    }
} else {
    echo "Not found\n";
}
