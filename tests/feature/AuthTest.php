<?php namespace AhmadFatoni\ApiGenerator\Tests\Feature;

use Config;
use Carbon\Carbon;
use AhmadFatoni\ApiGenerator\Tests\ApiTestCase;

/**
 * AuthTest covers the bearer token protection and the auth endpoints
 */
class AuthTest extends ApiTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->makeUser();
        $this->makeApi(['name' => 'Articles', 'endpoint' => 'api/v1/articles', 'model' => 'tailor:ApiTest\Article', 'require_auth' => true]);
        $this->makeApi(['name' => 'Authors', 'endpoint' => 'api/v1/authors', 'model' => 'tailor:ApiTest\Author']);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testProtectedApiRequiresAToken()
    {
        foreach ([
            ['GET', 'api/v1/articles'],
            ['GET', 'api/v1/articles/1'],
            ['POST', 'api/v1/articles'],
            ['PUT', 'api/v1/articles/1'],
            ['DELETE', 'api/v1/articles/1'],
            ['GET', 'api/v1/articles/1/delete'],
        ] as [$method, $url]) {
            $this->json($method, $url, ['title' => 'x'])
                ->assertStatus(401)
                ->assertExactJson([
                    'status_code' => 401,
                    'message' => 'unauthorized',
                    'data' => ['error' => 'A valid bearer token is required.'],
                ]);
        }
    }

    public function testPublicApiStaysPublic()
    {
        $this->getJson('api/v1/authors')->assertStatus(200);
    }

    public function testLoginReturnsAToken()
    {
        $response = $this->postJson('api/v1/auth/login', ['email' => 'api@example.tld', 'password' => 'ChangeMe888']);

        $response->assertStatus(200)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.user.email', 'api@example.tld');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function testLoginNeverExposesSecrets()
    {
        $user = $this->postJson('api/v1/auth/login', ['email' => 'api@example.tld', 'password' => 'ChangeMe888'])->json('data.user');

        foreach (['password', 'persist_code', 'activation_code', 'reset_password_code', 'two_factor_secret', 'two_factor_recovery_codes'] as $secret) {
            $this->assertArrayNotHasKey($secret, $user);
        }
    }

    public function testLoginWithWrongPasswordFails()
    {
        $this->postJson('api/v1/auth/login', ['email' => 'api@example.tld', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'unauthorized')
            ->assertJsonStructure(['data' => ['email']]);
    }

    public function testLoginWithMissingFieldsFails()
    {
        $this->postJson('api/v1/auth/login', [])->assertStatus(401);
    }

    public function testTokenGrantsAccess()
    {
        $token = $this->loginToken();

        $this->withToken($token)->postJson('api/v1/articles', ['title' => 'Hello', 'slug' => 'hello'])->assertStatus(201);

        $this->app['auth']->logout();

        $this->withToken($token)->getJson('api/v1/articles')->assertStatus(200)->assertJsonPath('data.0.title', 'Hello');
    }

    public function testInvalidTokenIsRejected()
    {
        $this->withToken('not.a.token')->getJson('api/v1/articles')->assertStatus(401);
    }

    public function testExpiredTokenIsRejected()
    {
        $token = $this->loginToken();

        Carbon::setTestNow(Carbon::now()->addMinutes(Config::get('rainlab.user::bearer_token.ttl') ?? 60)->addMinutes(5));

        $this->withToken($token)->getJson('api/v1/articles')->assertStatus(401);
    }

    public function testMeReturnsTheCurrentUser()
    {
        $token = $this->loginToken();

        $this->withToken($token)->getJson('api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'api@example.tld')
            ->assertJsonMissingPath('data.user.persist_code');

        $this->app['auth']->logout();

        $this->withoutToken()->getJson('api/v1/auth/me')->assertStatus(401);
    }

    public function testRefreshReturnsAWorkingToken()
    {
        $token = $this->loginToken();

        $newToken = $this->withToken($token)->postJson('api/v1/auth/refresh')
            ->assertStatus(200)
            ->json('data.token');

        $this->assertNotEmpty($newToken);

        $this->app['auth']->logout();

        $this->withToken($newToken)->getJson('api/v1/articles')->assertStatus(200);
    }

    public function testRefreshRequiresAToken()
    {
        $this->postJson('api/v1/auth/refresh')->assertStatus(401);
    }

    public function testTurningAuthOffMakesTheApiPublic()
    {
        $api = \AhmadFatoni\ApiGenerator\Models\ApiGenerator::where('name', 'Articles')->first();
        $api->require_auth = false;
        $api->save();
        $this->reloadRoutes();

        $this->getJson('api/v1/articles')->assertStatus(200);
    }
}
