<?php
namespace App\Providers;
use App\Services\{FakeRouteCalculator,OsrmRouteCalculator,RouteCalculatorInterface,WhatsAppNotifierInterface,WaMeManualNotifier};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RouteCalculatorInterface::class, fn () => app()->environment('testing') || config('services.routes.provider') === 'fake' ? new FakeRouteCalculator : new OsrmRouteCalculator);
        $this->app->bind(WhatsAppNotifierInterface::class, WaMeManualNotifier::class);
    }

    public function boot(): void
    {
        RateLimiter::for('order-preview', fn (Request $request) => [
            Limit::perMinute(20)->by('order-preview-form:'.($request->input('submission_token') ?: $request->session()->getId())),
            Limit::perMinute(60)->by('order-preview-ip:'.$request->ip()),
        ]);
        RateLimiter::for('order-submit', fn (Request $request) => [
            Limit::perMinute(5)->by('order-submit-form:'.($request->input('submission_token') ?: $request->session()->getId())),
            Limit::perMinute(30)->by('order-submit-ip:'.$request->ip()),
        ]);
        RateLimiter::for('telegram-webhook', fn (Request $request) => Limit::perMinute(60)->by('telegram-webhook:'.$request->ip()));
        RateLimiter::for('telegram-test', fn (Request $request) => Limit::perMinute(3)->by('telegram-test:'.$request->user()?->id));
    }
}
