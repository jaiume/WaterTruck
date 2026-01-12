<?php

declare(strict_types=1);

namespace WaterTruck\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WaterTruck\Services\IdentityService;
use WaterTruck\Services\ConfigService;
use WaterTruck\Services\UtilityService;

class IdentityMiddleware implements MiddlewareInterface
{
    private const DEVICE_TOKEN_HEADER = 'X-Device-Token';
    private const DEVICE_TOKEN_COOKIE = 'device_token';

    public function __construct(private IdentityService $identityService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get device token from header or cookie
        $deviceToken = $this->getDeviceToken($request);
        
        // If no token, generate one
        if ($deviceToken === null) {
            $deviceToken = UtilityService::generateUuid();
        }
        
        // Get or create user
        $user = $this->identityService->getOrCreateByDeviceToken($deviceToken);
        
        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('device_token', $deviceToken);
        
        // Process request
        $response = $handler->handle($request);
        
        // Set device token cookie (backup for localStorage)
        $response = $this->setDeviceTokenCookie($response, $deviceToken);
        
        return $response;
    }

    private function getDeviceToken(ServerRequestInterface $request): ?string
    {
        // Try header first
        $headerValues = $request->getHeader(self::DEVICE_TOKEN_HEADER);
        if (!empty($headerValues)) {
            $token = $headerValues[0];
            if ($this->isValidUuid($token)) {
                return $token;
            }
        }
        
        // Try cookie
        $cookies = $request->getCookieParams();
        if (isset($cookies[self::DEVICE_TOKEN_COOKIE])) {
            $token = $cookies[self::DEVICE_TOKEN_COOKIE];
            if ($this->isValidUuid($token)) {
                return $token;
            }
        }
        
        return null;
    }

    private function isValidUuid(string $token): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $token
        ) === 1;
    }

    private function setDeviceTokenCookie(ResponseInterface $response, string $deviceToken): ResponseInterface
    {
        $expires = time() + (365 * 24 * 60 * 60); // 1 year
        $domain = ConfigService::get('auth.cookie_domain', '');
        $secure = ConfigService::get('auth.cookie_secure', true) ? 'Secure; ' : '';
        $sameSite = ConfigService::get('auth.cookie_samesite', 'Lax');
        
        $cookie = sprintf(
            '%s=%s; Expires=%s; Path=/; %sHttpOnly; SameSite=%s',
            self::DEVICE_TOKEN_COOKIE,
            $deviceToken,
            gmdate('D, d M Y H:i:s T', $expires),
            $secure,
            $sameSite
        );
        
        if ($domain) {
            $cookie .= '; Domain=' . $domain;
        }
        
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }
}
