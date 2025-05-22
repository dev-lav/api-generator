<?php namespace AhmadFatoni\ApiGenerator\Tests;

use PluginTestCase as OctoberPluginTestCase;

/**
 * Plugin test case.
 */
class PluginTestCase extends OctoberPluginTestCase
{
    /**
     * @var array Plugins to refresh between tests.
     */
    protected $refreshPlugins = [
        'AhmadFatoni.ApiGenerator',
    ];

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Plugin specific setup if needed
    }

    /**
     * Tear down the test environment.
     */
    public function tearDown(): void
    {
        parent::tearDown();

        // Plugin specific teardown if needed
    }
}
