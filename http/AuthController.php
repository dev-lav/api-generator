<?php namespace AhmadFatoni\ApiGenerator\Http;

use Auth;
use Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RainLab\User\Classes\ActionManager;
use RainLab\User\Helpers\User as UserHelper;
use AhmadFatoni\ApiGenerator\Helpers\Helpers;
use October\Rain\Exception\ValidationException;
use Illuminate\Validation\ValidationException as LaravelValidationException;

/**
 * AuthController issues RainLab.User bearer tokens for the generated APIs
 */
class AuthController extends Controller
{
    protected $helpers;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    /**
     * login with the user login attribute (email or username) and password
     */
    public function login(Request $request)
    {
        $username = UserHelper::username();
        $input = $request->only([$username, 'password']);

        try {
            $result = ActionManager::instance()->loginWithTwoFactor($input);
        }
        catch (ValidationException $ex) {
            return $this->helpers->apiArrayResponseBuilder(401, 'unauthorized', $ex->getErrors()->toArray());
        }
        catch (LaravelValidationException $ex) {
            return $this->helpers->apiArrayResponseBuilder(401, 'unauthorized', $ex->errors());
        }

        if ($result === ActionManager::TWO_FACTOR_CHALLENGE) {
            return $this->helpers->apiArrayResponseBuilder(403, 'forbidden', ['error' => 'Two factor authentication is not supported by the API login.']);
        }

        if (!Auth::check()) {
            return $this->helpers->apiArrayResponseBuilder(401, 'unauthorized', ['error' => 'Login failed.']);
        }

        return $this->tokenResponse();
    }

    /**
     * refresh returns a new token for the user of the current token
     */
    public function refresh()
    {
        return $this->tokenResponse();
    }

    /**
     * me returns the user of the current token
     */
    public function me()
    {
        return $this->helpers->apiArrayResponseBuilder(200, 'success', ['user' => $this->userPayload()]);
    }

    /**
     * userPayload returns the public user attributes, never the persist or activation codes
     */
    protected function userPayload(): array
    {
        return array_only(Auth::user()->toArray(), [
            'id',
            'first_name',
            'last_name',
            'username',
            'email',
            'avatar_url',
            'activated_at',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * tokenResponse
     */
    protected function tokenResponse()
    {
        return $this->helpers->apiArrayResponseBuilder(200, 'success', [
            'token' => Auth::getBearerToken(),
            'token_type' => 'Bearer',
            'expires_in' => (Config::get('rainlab.user::bearer_token.ttl') ?? 60) * 60,
            'user' => $this->userPayload(),
        ]);
    }
}
