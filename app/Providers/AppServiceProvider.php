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
        //
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
