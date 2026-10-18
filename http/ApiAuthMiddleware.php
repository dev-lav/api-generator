<?php namespace AhmadFatoni\ApiGenerator\Http;

use Auth;
use Closure;
use AhmadFatoni\ApiGenerator\Helpers\Helpers;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;

/**
 * ApiAuthMiddleware authenticates the request with a RainLab.User bearer token
 * and responds with JSON when the token is missing or invalid.
 */
class ApiAuthMiddleware
{
    /**
     * handle the request
     */
    public function handle($request, Closure $next)
    {
        $helpers = new Helpers;

        if (!ApiGenerator::hasUserPlugin()) {
            return $helpers->apiArrayResponseBuilder(500, 'error', ['error' => 'Authentication requires the RainLab.User plugin.']);
        }

        if ($jwtToken = $request->bearerToken()) {
            Auth::loginUsingBearerToken($jwtToken);
        }

        if (!Auth::check()) {
            return $helpers->apiArrayResponseBuilder(401, 'unauthorized', ['error' => 'A valid bearer token is required.']);
        }

        return $next($request);
    }
}
