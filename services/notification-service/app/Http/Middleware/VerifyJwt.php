<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification JWT sur les routes protegees.
 * Injecte les claims dans la requete : jwt.sub, jwt.permissions, jwt.structure_id.
 */
class VerifyJwt
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->error('UNAUTHENTICATED', 'Jeton d\'accès manquant.', 401);
        }

        // Appel inter-services : jeton statique partage (SERVICE_TOKEN).
        $serviceToken = env('SERVICE_TOKEN');
        if ($serviceToken && hash_equals($serviceToken, $token)) {
            $request->attributes->set('jwt', [
                'sub' => 'service',
                'permissions' => ['*'],
                'structure_id' => null,
            ]);
            return $next($request);
        }

        $claims = Jwt::decode($token);
        if (! $claims || empty($claims['sub'])) {
            return $this->error('TOKEN_INVALID', 'Jeton invalide ou expire.', 401);
        }
        // Un refresh token (type=refresh) n'est pas un jeton d'acces :
        // il ne doit jamais autoriser les routes metier.
        if (($claims['type'] ?? 'access') !== 'access') {
            return $this->error('TOKEN_INVALID', 'Jeton de type incorrect.', 401);
        }

        $request->attributes->set('jwt', $claims);
        return $next($request);
    }
}
