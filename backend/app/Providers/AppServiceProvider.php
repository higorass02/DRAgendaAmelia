<?php

namespace App\Providers;

use App\Models\Patient;
use App\Models\Professional;
use App\Observers\PatientAuditObserver;
use App\Observers\ProfessionalAuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Combina IP + conta para dificultar brute-force sem penalizar um IP
        // compartilhado (ex.: NAT) inteiro por causa de uma única conta.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        RateLimiter::for('writes', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Patient/Professional têm CRUD simples direto no controller (sem
        // camada de Actions própria) — auditar via observer evita espalhar
        // AuditLog::record() em cada método. Appointment/User/Account logam
        // explicitamente onde o ator já está à mão (Actions/controllers).
        Patient::observe(PatientAuditObserver::class);
        Professional::observe(ProfessionalAuditObserver::class);
    }
}
