<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\BaseController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomTokenValidation extends BaseController
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);
        if (! $token) {
            return $this->unauthorizedResponse('Authorization header not found');
        }
        $decoded = $this->decodeJwtToken($token);
        if (! $decoded) {
            return $this->unauthorizedResponse('Token is invalid or expired');
        }
        $claimsArray = $this->getDecryptedClaims($decoded);
        if (! $claimsArray) {
            return $this->unauthorizedResponse('Token is invalid or expired');
        }
        $user = $this->getUserFromClaims($claimsArray);
        if (! $user) {
            return $this->unauthorizedResponse('User not found');
        }
        $request->attributes->add(['decoded_token' => collect($claimsArray)]);

        return $next($request);
    }
    /**
     * Decrypts the encrypted claim data from the token payload.
     *
     * @param string $encrypted The encrypted data string from the token.
     *
     * @return array The decrypted claims as an associative array.
     */
    protected function decryptClaims(string $encrypted): array
    {
        $decryptedJson = Crypt::decryptString($encrypted);
        return json_decode($decryptedJson, true);
    }
    /**
     * Returns a JSON response with an unauthorized status and message.
     *
     * @param string $message The error message to return.
     * @param int $status The HTTP status code (defaults to 401 Unauthorized).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message, int $status = 401)
    {
        return $this->sendError('Unauthorised.', ['error' => [$message]], $status);
    }
    /**
     * Extracts the Bearer token from the Authorization header in the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string|null The token string if found, otherwise null.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $authorizationHeader = $request->header('Authorization');

        if (! $authorizationHeader || ! preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }
    /**
     * Decodes the JWT token and checks for expiration.
     *
     * @param string $token The raw JWT token.
     *
     * @return object|null The decoded payload object, or null if invalid or expired.
     */
    private function decodeJwtToken(string $token): ?object
    {
        try {
            $decoded = JWTAuth::setToken($token)->getPayload();

            if ($decoded->get('exp') < time()) {
                return null;
            }

            return $decoded;
        } catch (
            \Tymon\JWTAuth\Exceptions\TokenExpiredException |
            \Tymon\JWTAuth\Exceptions\TokenInvalidException |
            \Tymon\JWTAuth\Exceptions\JWTException |
            \Exception) {
                return null;
            }
    }
    /**
     * Retrieves and decrypts the claims from the decoded token payload.
     *
     * @param object $decoded The decoded JWT payload.
     *
     * @return array|null Decrypted claims as an associative array, or null on failure.
     */
    private function getDecryptedClaims($decoded): ?array
    {
        try {
            return $this->decryptClaims($decoded->get('encryptedData'));
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * Finds and returns the user based on the ID found in the claims.
     *
     * @param array $claims The decrypted claims array from the token.
     *
     * @return \App\Models\User|null The corresponding user model, or null if not found.
     */
    private function getUserFromClaims(array $claims): ?\App\Models\User
    {
        return \App\Models\User::find($claims['id'] ?? null);
    }
}
