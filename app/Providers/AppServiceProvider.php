<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\DamageRecord;
use App\Models\Fine;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\MembershipPayment;
use App\Models\Payment;
use App\Models\Renewal;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Observers\BookCopyInventoryObserver;
use App\Services\PradeshiyaSabhaContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PradeshiyaSabhaContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        foreach ([
            User::class,
            Member::class,
            Book::class,
            BookCopy::class,
            Borrowing::class,
            Renewal::class,
            Payment::class,
            MembershipPayment::class,
            Fine::class,
            LostBook::class,
            DamageRecord::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        BookCopy::observe(BookCopyInventoryObserver::class);
    }
}
