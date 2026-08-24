<?php

namespace App\Repositories\Traits;

use Illuminate\Support\Facades\Cache;
use Throwable;

trait ScopedCache
{
    protected function rememberScoped(string $domain, string $suffix, int $seconds, callable $callback): mixed
    {
        $scope = $this->cacheScope();

        try {
            $version = Cache::get($this->versionKey($domain, $scope), 1);
            $cacheKey = "repo_cache:{$domain}:{$scope}:v{$version}:{$suffix}";

            return Cache::remember($cacheKey, $seconds, $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    protected function bumpScopedCache(array $domains): void
    {
        $scope = $this->cacheScope();

        foreach ($domains as $domain) {
            try {
                $versionKey = $this->versionKey($domain, $scope);
                $current = (int) Cache::get($versionKey, 1);
                Cache::forever($versionKey, $current + 1);
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function versionKey(string $domain, string $scope): string
    {
        return "repo_cache_version:{$domain}:{$scope}";
    }

    private function cacheScope(): string
    {
        $user = auth()->user();

        if ($user && !empty($user->org_id)) {
            return 'org:' . $user->org_id;
        }

        return 'user:' . auth()->id();
    }
}
