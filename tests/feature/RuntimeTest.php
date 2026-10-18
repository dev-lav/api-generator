<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use DB;
use File;
use Schema;
use AhmadFatoni\ApiGenerator\Classes\ApiRegistry;
use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * RuntimeTest covers serving the APIs without generated files
 */
class RuntimeTest extends ApiTestCase
{
    public function testSavingAnApiWritesNoFiles()
    {
        $pluginPath = plugins_path('ahmadfatoni/apigenerator');
        $routes = File::get($pluginPath . '/routes.php');
        $controllers = glob($pluginPath . '/controllers/api/*.php') ?: [];

        $api = $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);
        $api->delete();

        $this->assertEquals($routes, File::get($pluginPath . '/routes.php'));
        $this->assertEquals($controllers, glob($pluginPath . '/controllers/api/*.php') ?: []);
    }

    public function testApisSavedOutsideTheBackendWorkAfterTheCacheIsCleared()
    {
        // As after a deploy or a database import, no model events have run
        DB::table('ahmadfatoni_apigenerator_data')->insert([
            'name' => 'Articles',
            'endpoint' => 'api/v1/articles',
            'model' => 'tailor:ApiTest\Article',
            'description' => '',
            'require_auth' => false,
        ]);

        ApiRegistry::clearCache();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(200);
    }

    public function testMissingModelReturnsAnError()
    {
        DB::table('ahmadfatoni_apigenerator_data')->insert([
            'name' => 'Ghosts',
            'endpoint' => 'api/v1/ghosts',
            'model' => 'Acme\Removed\Models\Ghost',
            'description' => '',
            'require_auth' => false,
        ]);

        ApiRegistry::clearCache();
        $this->reloadRoutes();

        $this->getJson('api/v1/ghosts')
            ->assertStatus(500)
            ->assertJsonPath('data.error', 'The model for this API is not available.');
    }

    public function testNonDatabaseModelReturnsAnError()
    {
        DB::table('ahmadfatoni_apigenerator_data')->insert([
            'name' => 'Langs',
            'endpoint' => 'api/v1/langs',
            'model' => 'RainLab\Builder\Models\LocalizationModel',
            'description' => '',
            'require_auth' => false,
        ]);

        ApiRegistry::clearCache();
        $this->reloadRoutes();

        $this->getJson('api/v1/langs')->assertStatus(500)->assertJsonPath('message', 'error');
    }

    public function testRoutesLoadBeforeThePluginIsMigrated()
    {
        Schema::drop('ahmadfatoni_apigenerator_data');
        ApiRegistry::clearCache();

        $this->reloadRoutes();

        $this->assertEquals([], ApiRegistry::all());
    }

    public function testDefinitionsAreCachedUntilAnApiIsSaved()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);
        ApiRegistry::all();

        DB::enableQueryLog();
        ApiRegistry::all();
        $this->assertCount(0, DB::getQueryLog());

        $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);
        $this->assertCount(2, ApiRegistry::all());
    }

    public function testRouteNamesStayCompatible()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $this->assertEquals(url('api/v1/articles/5/delete'), route('api/v1/articles.delete', ['id' => 5]));
    }
}
