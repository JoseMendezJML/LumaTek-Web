<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\ClaudeChatAssistant;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChatAssistant::class, fn () => new ClaudeChatAssistant());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $trustedProxies = trim((string) config('app.trusted_proxies'));

        // Local Caddy terminates TLS and connects to Laravel over IPv4 loopback.
        // Preserve explicit deployment settings and direct HTTP development.
        if ($trustedProxies === '' && $this->app->environment('local')) {
            $trustedProxies = '127.0.0.1';
        }

        if ($trustedProxies !== '') {
            TrustProxies::at(
                $trustedProxies === '*'
                    ? '*'
                    : array_map('trim', explode(',', $trustedProxies))
            );
        }

        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && $event->user instanceof User) {
                session()->put(User::AUTH_VERSION_SESSION_KEY, [
                    'user_id' => $event->user->getAuthIdentifier(),
                    'version' => $event->user->authenticationVersion(),
                ]);
            }
        });
    }
}
