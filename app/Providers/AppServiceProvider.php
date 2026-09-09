<?php

namespace App\Providers;

use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\SelfPacedCourse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->useCloudStorageForPublicDisk();
    }

    /**
     * Every upload/URL call site in this app targets the 'public' disk by
     * name (Storage::disk('public')), which is hardcoded in config/
     * filesystems.php to local disk — fine for local dev, but Laravel
     * Cloud's compute has no persistent local filesystem, so anything
     * written there is unreliable/ephemeral in production.
     *
     * Laravel Cloud instead injects its own S3-compatible disk (backed by
     * R2) via LARAVEL_CLOUD_DISK_CONFIG, parsed by the framework's
     * Illuminate\Foundation\Cloud::configureDisks() — which runs during
     * bootstrapping, before this provider, and points filesystems.default
     * at whichever injected disk is marked default. Rather than rewrite
     * every call site to a Cloud-specific disk name, alias 'public' to
     * that disk here, so existing code transparently starts using Cloud's
     * persistent storage in production while local dev (no Cloud env,
     * default disk stays 'local') is untouched.
     */
    private function useCloudStorageForPublicDisk(): void
    {
        $defaultDisk = config('filesystems.default');

        if ($defaultDisk !== 'public' && config("filesystems.disks.{$defaultDisk}.driver") === 's3') {
            config(['filesystems.disks.public' => config("filesystems.disks.{$defaultDisk}")]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Plain morphMap (not enforceMorphMap): this app has other
        // polymorphic relations (e.g. Sanctum's tokenable_type on User)
        // that store a raw FQCN and were never meant to be constrained by
        // this map — enforcing it app-wide broke token creation. Only
        // order_items.product_type needs a stable alias.
        Relation::morphMap([
            ProductType::CourseOffering->value => SelfPacedCourse::class,
            ProductType::TutoringServiceBooking->value => Booking::class,
        ]);
    }
}
