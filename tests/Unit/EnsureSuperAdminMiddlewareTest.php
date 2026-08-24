<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureSuperAdminMiddlewareTest extends TestCase
{
    public function test_it_returns_401_for_guests(): void
    {
        $middleware = new EnsureSuperAdmin();
        $request = Request::create('/api/super-admin/users', 'GET');

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_it_returns_403_for_non_super_admin_users(): void
    {
        $middleware = new EnsureSuperAdmin();
        $request = Request::create('/api/super-admin/users', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public function isSuperAdmin(): bool
            {
                return false;
            }
        });

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_it_allows_super_admin_users(): void
    {
        $middleware = new EnsureSuperAdmin();
        $request = Request::create('/api/super-admin/users', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public function isSuperAdmin(): bool
            {
                return true;
            }
        });

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }
}
