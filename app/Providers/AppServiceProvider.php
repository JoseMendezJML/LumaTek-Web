<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\ClaudeChatAssistant;
use Illuminate\Auth\Events\Login;
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
