<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use October\Rain\Exception\ValidationException;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;
use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * ApiLifecycleTest covers creating, renaming and deleting API definitions
 */
class ApiLifecycleTest extends ApiTestCase
{
    public function testSavingAnApiExposesItsEndpoints()
    {
        $this->getJson('api/v1/articles')->assertStatus(404);

        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $this->getJson('api/v1/articles')->assertStatus(200)->assertJsonPath('message', 'success');
    }

    public function testEndpointWithLeadingSlashIsNormalized()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => '/api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $this->getJson('api/v1/articles')->assertStatus(200);
    }

    public function testChangingTheEndpointMovesTheApi()
    {
        $api = $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $api->endpoint = 'api/v2/articles';
        $api->save();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(404);
        $this->getJson('api/v2/articles')->assertStatus(200);
    }

    public function testRenamingTheApiKeepsTheEndpointWorking()
    {
        $api = $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $api->name = 'News Articles';
        $api->save();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(200);
    }

    public function testChangingTheModelChangesTheData()
    {
        $this->postArticle('Hello');

        $api = $this->makeApi(['name' => 'Content', 'endpoint' => 'api/v1/content', 'model' => 'tailor:ApiTest\Article']);
        $this->getJson('api/v1/content')->assertJsonCount(1, 'data');

        $api->model = 'tailor:ApiTest\Author';
        $api->save();
        $this->reloadRoutes();

        $this->getJson('api/v1/content')->assertStatus(200)->assertJsonMissingPath('data');
    }

    public function testDeletingAnApiRemovesItsEndpoints()
    {
        $api = $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        $api->delete();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(404);
    }

    public function testSavingOrDeletingOneApiKeepsTheOthers()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);
        $authors = $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);
        $this->makeApi(['name' => 'Settings', 'endpoint' => 'api/v1/settings', 'model' => 'tailor:ApiTest\Settings']);

        $authors->delete();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(200);
        $this->getJson('api/v1/authors')->assertStatus(404);
        $this->getJson('api/v1/settings')->assertStatus(200);
    }

    /**
     * @dataProvider invalidApiProvider
     */
    public function testInvalidDefinitionsAreRejected(array $attributes, string $field)
    {
        try {
            $this->makeApi(array_merge([
                'name' => 'Articles',
                'endpoint' => 'api/v1/articles',
                'model' => 'tailor:ApiTest\Article',
            ], $attributes));

            $this->fail('Expected a validation error on ' . $field);
        }
        catch (ValidationException $ex) {
            $this->assertArrayHasKey($field, $ex->getErrors()->toArray());
        }

        $this->assertEquals(0, ApiGenerator::count());
    }

    public static function invalidApiProvider()
    {
        return [
            'empty name' => [['name' => ''], 'name'],
            'name with path' => [['name' => '../Evil'], 'name'],
            'name starting with a digit' => [['name' => '1Articles'], 'name'],
            'empty endpoint' => [['endpoint' => ''], 'endpoint'],
            'endpoint with quotes' => [['endpoint' => "api/v1/x'); system('id"], 'endpoint'],
            'unknown model' => [['model' => 'Acme\Blog\Models\Missing'], 'model'],
            'code injection in model' => [['model' => 'Foo;system(1)'], 'model'],
            'unknown tailor handle' => [['model' => 'tailor:Missing\Handle'], 'model'],
            'invalid custom condition' => [['custom_format' => '{"nope": 1}'], 'custom_format'],
        ];
    }

    public function testDuplicateNamesAndEndpointsAreRejected()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);

        foreach ([
            'name' => ['name' => 'Articles', 'endpoint' => 'api/v1/other'],
            'endpoint' => ['name' => 'Other', 'endpoint' => 'api/v1/articles'],
        ] as $field => $attributes) {
            try {
                $this->makeApi($attributes + ['model' => 'tailor:ApiTest\Article']);
                $this->fail('Expected a validation error on ' . $field);
            }
            catch (ValidationException $ex) {
                $this->assertArrayHasKey($field, $ex->getErrors()->toArray());
            }
        }
    }

    public function testModelOptionsListPluginModelsAndTailorBlueprints()
    {
        $options = (new ApiGenerator)->getModelOptions();

        $this->assertArrayHasKey('RainLab\User\Models\User', $options);
        $this->assertArrayHasKey('tailor:ApiTest\Article', $options);
        $this->assertArrayHasKey('tailor:ApiTest\Author', $options);
        $this->assertArrayHasKey('tailor:ApiTest\Settings', $options);
        $this->assertArrayNotHasKey(ApiGenerator::class, $options);
    }

    protected function postArticle(string $title)
    {
        $this->makeApi(['name' => 'Seed', 'endpoint' => 'api/seed', 'model' => 'tailor:ApiTest\Article']);
        $this->postJson('api/seed', ['title' => $title, 'slug' => str_slug($title)])->assertStatus(201);
    }
}
