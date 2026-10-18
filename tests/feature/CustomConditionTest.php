<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use RainLab\User\Models\UserGroup;
use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * CustomConditionTest covers limiting columns and eager loading relations
 */
class CustomConditionTest extends ApiTestCase
{
    public function testFillableLimitsTheColumns()
    {
        $this->makeApi([
            'name' => 'Articles',
            'endpoint' => 'api/v1/articles',
            'model' => 'tailor:ApiTest\Article',
            'custom_format' => '{"fillable": "id,title"}',
        ]);

        $id = $this->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello', 'summary' => 'Hidden'])->json('data.id');

        $this->assertEquals(['id', 'title'], array_keys($this->getJson('api/v1/articles')->json('data.0')));
        $this->assertEquals(['id', 'title'], array_keys($this->getJson("api/v1/articles/{$id}")->json('data.0')));
    }

    public function testFillableToleratesSpaces()
    {
        $this->makeApi([
            'name' => 'Articles',
            'endpoint' => 'api/v1/articles',
            'model' => 'tailor:ApiTest\Article',
            'custom_format' => '{"fillable": "id, title"}',
        ]);

        $this->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello'])->assertStatus(201);

        $this->assertEquals(['id', 'title'], array_keys($this->getJson('api/v1/articles')->json('data.0')));
    }

    public function testRelationsAreEagerLoadedWithTheirColumns()
    {
        $group = UserGroup::create(['name' => 'Editors', 'code' => 'editors']);
        $this->makeUser(['primary_group_id' => $group->id]);

        $this->makeApi([
            'name' => 'Members',
            'endpoint' => 'api/v1/members',
            'model' => 'RainLab\User\Models\User',
            'custom_format' => json_encode([
                'fillable' => 'id,email,primary_group_id',
                'relation' => [['name' => 'primary_group', 'fillable' => 'id,name']],
            ]),
        ]);

        $member = $this->getJson('api/v1/members')->assertStatus(200)->json('data.0');

        $this->assertEquals('api@example.tld', $member['email']);
        $this->assertArrayNotHasKey('first_name', $member);
        $this->assertEquals(['id' => $group->id, 'name' => 'Editors'], $member['primary_group']);
    }

    public function testRelationWithoutFillableLoadsAllColumns()
    {
        $group = UserGroup::create(['name' => 'Editors', 'code' => 'editors']);
        $this->makeUser(['primary_group_id' => $group->id]);

        $this->makeApi([
            'name' => 'Members',
            'endpoint' => 'api/v1/members',
            'model' => 'RainLab\User\Models\User',
            'custom_format' => '{"relation": [{"name": "primary_group"}]}',
        ]);

        $this->getJson('api/v1/members')
            ->assertStatus(200)
            ->assertJsonPath('data.0.primary_group.code', 'editors');
    }

    public function testTailorRelationsAreEagerLoaded()
    {
        $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);
        $authorId = $this->postJson('api/v1/authors', ['title' => 'Jane', 'slug' => 'jane', 'bio' => 'Writer'])->json('data.id');

        $this->makeApi([
            'name' => 'Articles',
            'endpoint' => 'api/v1/articles',
            'model' => 'tailor:ApiTest\Article',
            'custom_format' => json_encode([
                'fillable' => 'id,title,author_id',
                'relation' => [['name' => 'author', 'fillable' => 'id,title']],
            ]),
        ]);

        $this->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello', 'author_id' => $authorId])->assertStatus(201);

        $this->getJson('api/v1/articles')
            ->assertStatus(200)
            ->assertJsonPath('data.0.author', ['id' => $authorId, 'title' => 'Jane']);
    }
}
