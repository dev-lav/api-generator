<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * TailorTest covers APIs built on Tailor sections and globals
 */
class TailorTest extends ApiTestCase
{
    public function testSectionsOnlyReturnTheirOwnRecords()
    {
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article']);
        $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);

        $articleId = $this->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello'])->json('data.id');
        $authorId = $this->postJson('api/v1/authors', ['title' => 'Jane', 'slug' => 'jane'])->json('data.id');

        $this->getJson('api/v1/articles')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Hello');
        $this->getJson('api/v1/authors')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Jane');

        // Each section has its own table, so ids may overlap, but titles never leak across
        $this->assertEquals('Hello', $this->getJson("api/v1/articles/{$articleId}")->json('data.0.title'));
        $this->assertEquals('Jane', $this->getJson("api/v1/authors/{$authorId}")->json('data.0.title'));
    }

    public function testCustomFieldsAreStoredAndReturned()
    {
        $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);

        $id = $this->postJson('api/v1/authors', ['title' => 'Jane', 'slug' => 'jane', 'bio' => 'Writer'])->json('data.id');

        $this->getJson("api/v1/authors/{$id}")->assertJsonPath('data.0.bio', 'Writer');
    }

    public function testGlobalsCanBeReadAndUpdated()
    {
        $this->makeApi(['name' => 'Settings', 'endpoint' => 'api/v1/settings', 'model' => 'tailor:ApiTest\Settings']);

        // Make sure the global record exists
        \Tailor\Models\GlobalRecord::findForGlobal('ApiTest\Settings');

        $global = $this->getJson('api/v1/settings')->assertStatus(200)->assertJsonCount(1, 'data')->json('data.0');

        $this->putJson("api/v1/settings/{$global['id']}", ['site_name' => 'My Site'])->assertStatus(200);

        $this->getJson("api/v1/settings/{$global['id']}")->assertJsonPath('data.0.site_name', 'My Site');
    }
}
