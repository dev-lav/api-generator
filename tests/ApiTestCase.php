<?php namespace AhmadFatoni\ApiGenerator\Tests;

use File;
use Route;
use PluginTestCase;
use ReflectionProperty;
use Tailor\Classes\Blueprint;
use Tailor\Classes\BlueprintIndexer;
use RainLab\User\Models\User;
use Illuminate\Routing\RouteCollection;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;

/**
 * ApiTestCase boots the plugin with RainLab.User and the fixture Tailor blueprints,
 * the tests only talk to the APIs over HTTP so they survive internal refactors.
 */
abstract class ApiTestCase extends PluginTestCase
{
    /**
     * @var array generatedFiles snapshot of files the generator may write, restored after each test
     */
    protected $generatedFiles = [];

    /**
     * setUp
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->snapshotGeneratedFiles();

        $this->loadPlugin('RainLab.User');
        $this->migratePlugin('RainLab.User');

        $this->registerFixtureBlueprints();

        $this->reloadRoutes();
    }

    /**
     * tearDown
     */
    public function tearDown(): void
    {
        $this->restoreGeneratedFiles();

        $this->setResolvedBlueprintPlugins(null);

        parent::tearDown();
    }

    /**
     * makeApi saves an API definition and reloads the routes
     */
    protected function makeApi(array $attributes): ApiGenerator
    {
        $api = new ApiGenerator;
        $api->forceFill(array_merge(['description' => ''], $attributes));
        $api->save();

        $this->reloadRoutes();

        return $api;
    }

    /**
     * reloadRoutes registers the plugin routes again, as a new request would
     */
    protected function reloadRoutes()
    {
        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection);

        $routesFile = plugins_path('ahmadfatoni/apigenerator/routes.php');
        if (file_exists($routesFile)) {
            require $routesFile;
        }

        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }

    /**
     * makeUser creates an activated frontend user
     */
    protected function makeUser(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'first_name' => 'Api',
            'last_name' => 'Tester',
            'email' => 'api@example.tld',
            'password' => 'ChangeMe888',
            'password_confirmation' => 'ChangeMe888',
            'activated_at' => now(),
        ], $attributes));
        $user->save();

        return $user;
    }

    /**
     * loginToken signs in through the API and returns the bearer token
     */
    protected function loginToken(string $email = 'api@example.tld', string $password = 'ChangeMe888'): string
    {
        $response = $this->postJson('api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $response->assertStatus(200);

        // Each request in a real app starts signed out
        $this->app['auth']->logout();

        return $response->json('data.token');
    }

    /**
     * registerFixtureBlueprints adds tests/fixtures/blueprints as a Tailor source and migrates it
     */
    protected function registerFixtureBlueprints()
    {
        $this->setResolvedBlueprintPlugins([
            'ApiTest.Fixtures' => __DIR__ . '/fixtures/blueprints'
        ]);

        BlueprintIndexer::instance()->flushCache();
        BlueprintIndexer::instance()->migrate();
    }

    /**
     * setResolvedBlueprintPlugins overrides the plugin blueprint paths Tailor scans
     */
    protected function setResolvedBlueprintPlugins($value)
    {
        $property = new ReflectionProperty(Blueprint::class, 'resolvedPlugins');
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    /**
     * snapshotGeneratedFiles
     */
    protected function snapshotGeneratedFiles()
    {
        $pluginPath = plugins_path('ahmadfatoni/apigenerator');

        $this->generatedFiles = [];

        foreach (array_merge([$pluginPath . '/routes.php'], glob($pluginPath . '/controllers/api/*.php') ?: []) as $file) {
            $this->generatedFiles[$file] = File::get($file);
        }
    }

    /**
     * restoreGeneratedFiles removes files written by the test and restores the originals
     */
    protected function restoreGeneratedFiles()
    {
        $pluginPath = plugins_path('ahmadfatoni/apigenerator');

        foreach (glob($pluginPath . '/controllers/api/*.php') ?: [] as $file) {
            if (!isset($this->generatedFiles[$file])) {
                File::delete($file);
            }
        }

        foreach ($this->generatedFiles as $file => $contents) {
            File::put($file, $contents);
        }
    }
}
