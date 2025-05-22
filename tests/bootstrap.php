<?php

require __DIR__.'/../vendor/autoload.php';

// Include the OctoberCMS test case
// This path might need adjustment depending on the OctoberCMS version and project structure
// For newer versions (October CMS 3.x+ or Winter CMS), it's often found in the vendor directory
$octoberTestCasePath = __DIR__.'/../../../october/rain/src/Testing/PluginTestCase.php'; // Common path for October v1/v2 in a typical project setup
$winterTestCasePath = __DIR__.'/../../../winter/storm/src/Testing/PluginTestCase.php'; // Common path for Winter CMS

if (file_exists($octoberTestCasePath)) {
    require_once $octoberTestCasePath;
} elseif (file_exists($winterTestCasePath)) {
    require_once $winterTestCasePath;
} else {
    // Fallback or error if PluginTestCase is not found
    // For a generic PHPUnit setup without October/Winter specific testing helpers,
    // this part might not be strictly necessary if you're only doing unit tests
    // that don't interact with the CMS.
    echo "Warning: October/Winter CMS PluginTestCase.php not found. Full CMS testing features might not be available.\n";
    // If you have a custom test case or a different way to bootstrap, include it here.
}

// Optional: If you have a custom PluginTestCase for your plugin
if (file_exists(__DIR__.'/PluginTestCase.php')) {
    require_once __DIR__.'/PluginTestCase.php';
}

// Further bootstrapping for OctoberCMS environment if needed
// This can be complex and might involve:
// - Setting up a dummy app container
// - Registering service providers
// - Booting the CMS kernel

// For now, we'll keep it simple. If tests fail due to missing CMS services,
// this section will need to be expanded.

echo "Bootstrap file loaded.\n";
