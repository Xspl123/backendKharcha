<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckPermissionMiddlewareTest extends TestCase
{
    public function test_it_returns_401_for_unauthenticated_requests(): void
    {
        $middleware = new CheckPermission();
        $request = Request::create('/api/products', 'GET');

        $response = $middleware->handle($request, fn () => response('ok'), 'products.view');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_it_blocks_inactive_users(): void
    {
        $middleware = new CheckPermission();
        $request = Request::create('/api/products', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public bool $is_active = false;

            public function hasPermission(string $permission): bool
            {
                return true;
            }
        });

        $response = $middleware->handle($request, fn () => response('ok'), 'products.view');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_allows_authorized_active_users(): void
    {
        $middleware = new CheckPermission();
        $request = Request::create('/api/products', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public bool $is_active = true;

            public function hasPermission(string $permission): bool
            {
                return $permission === 'products.view';
            }

            public function __get(string $name)
            {
                return null;
            }
        });

        $response = $middleware->handle($request, fn () => response('ok', 200), 'products.view');

        $this->assertSame(200, $response->getStatusCode());
    }
}
