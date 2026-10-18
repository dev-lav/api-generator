<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * CrudTest covers the generated REST endpoints and their response format
 */
class CrudTest extends ApiTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);
    }

    public function testIndexWithoutRecordsOmitsData()
    {
        $this->getJson('api/v1/articles')
            ->assertStatus(200)
            ->assertExactJson(['status_code' => 200, 'message' => 'success']);
    }

    public function testStoreCreatesARecord()
    {
        $response = $this->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello', 'summary' => 'First']);

        $response->assertStatus(201)
            ->assertJsonPath('status_code', 201)
            ->assertJsonPath('message', 'created');

        $id = $response->json('data.id');
        $this->assertNotEmpty($id);

        $this->getJson('api/v1/articles')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.summary', 'First');
    }

    public function testStoreAcceptsFormEncodedBodies()
    {
        $this->post('api/v1/articles', ['title' => 'Form', 'slug' => 'form'])->assertStatus(201);

        $this->getJson('api/v1/articles')->assertJsonPath('data.0.title', 'Form');
    }

    public function testShowReturnsTheRecordWrappedInAList()
    {
        $id = $this->createArticle('Hello');

        $this->getJson("api/v1/articles/{$id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Hello');
    }

    public function testShowMissingRecordReturns404()
    {
        $this->getJson('api/v1/articles/999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'not found')
            ->assertJsonPath('data.error', 'Resource id=999 could not be found');
    }

    /**
     * @dataProvider updateMethodProvider
     */
    public function testUpdateChangesTheRecord(string $method)
    {
        $id = $this->createArticle('Hello');

        $this->json($method, "api/v1/articles/{$id}", ['title' => 'Updated'])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->getJson("api/v1/articles/{$id}")->assertJsonPath('data.0.title', 'Updated');
    }

    public static function updateMethodProvider()
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH']];
    }

    public function testUpdateAcceptsFormEncodedBodies()
    {
        $id = $this->createArticle('Hello');

        $this->put("api/v1/articles/{$id}", ['title' => 'Form'])->assertStatus(200);

        $this->getJson("api/v1/articles/{$id}")->assertJsonPath('data.0.title', 'Form');
    }

    public function testUpdateIgnoresTheIdInTheBody()
    {
        $id = $this->createArticle('Hello');

        $this->putJson("api/v1/articles/{$id}", ['id' => 999, 'title' => 'Updated'])->assertStatus(200);

        $this->getJson("api/v1/articles/{$id}")->assertJsonPath('data.0.title', 'Updated');
        $this->getJson('api/v1/articles/999')->assertStatus(404);
    }

    public function testUpdateMissingRecordReturns404()
    {
        $this->putJson('api/v1/articles/999', ['title' => 'Updated'])->assertStatus(404);
    }

    public function testUnknownFieldsReturn400()
    {
        $this->postJson('api/v1/articles', ['title' => 'Hello', 'no_such_column' => 1])
            ->assertStatus(400)
            ->assertJsonPath('status_code', 400);

        $id = $this->createArticle('Hello');

        $this->putJson("api/v1/articles/{$id}", ['no_such_column' => 1])->assertStatus(400);
    }

    public function testDestroyDeletesTheRecord()
    {
        $id = $this->createArticle('Hello');

        $this->deleteJson("api/v1/articles/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.message', 'Data has been deleted successfully.');

        $this->getJson("api/v1/articles/{$id}")->assertStatus(404);
    }

    public function testLegacyGetDeleteRouteDeletesTheRecord()
    {
        $id = $this->createArticle('Hello');

        $this->getJson("api/v1/articles/{$id}/delete")->assertStatus(200);

        $this->getJson("api/v1/articles/{$id}")->assertStatus(404);
    }

    public function testDestroyMissingRecordReturns404()
    {
        $this->deleteJson('api/v1/articles/999')->assertStatus(404);
        $this->getJson('api/v1/articles/999/delete')->assertStatus(404);
    }

    public function testCreateAndEditRoutesAreNotExposed()
    {
        $this->getJson('api/v1/articles/create')->assertStatus(404);

        $id = $this->createArticle('Hello');
        $this->getJson("api/v1/articles/{$id}/edit")->assertStatus(404);
    }

    public function testPluginModelApi()
    {
        $user = $this->makeUser();
        $this->makeApi(['name' => 'Members', 'endpoint' => 'api/v1/members', 'model' => 'RainLab\User\Models\User']);

        $this->getJson('api/v1/members')
            ->assertStatus(200)
            ->assertJsonPath('data.0.email', 'api@example.tld');

        $this->getJson("api/v1/members/{$user->id}")->assertJsonPath('data.0.first_name', 'Api');

        $this->putJson("api/v1/members/{$user->id}", ['first_name' => 'Changed'])->assertStatus(200);
        $this->assertEquals('Changed', $user->fresh()->first_name);
    }

    public function testPluginModelValidationReturns400()
    {
        $this->makeApi(['name' => 'Members', 'endpoint' => 'api/v1/members', 'model' => 'RainLab\User\Models\User']);

        $this->postJson('api/v1/members', ['first_name' => 'No Email'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'fail')
            ->assertJsonStructure(['data' => ['email']]);
    }

    protected function createArticle(string $title)
    {
        return $this->postJson('api/v1/articles', ['title' => $title, 'slug' => str_slug($title)])
            ->assertStatus(201)
            ->json('data.id');
    }
}
