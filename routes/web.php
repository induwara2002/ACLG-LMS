<?php

use App\Http\Controllers\AdminAuthorController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminBookController;
use App\Http\Controllers\AdminBookCopyController;
use App\Http\Controllers\AdminBorrowingController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminFineController;
use App\Http\Controllers\AdminLostDamagedController;
use App\Http\Controllers\AdminMemberController;
use App\Http\Controllers\AdminMemberApplicationController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminPublisherController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminRenewalController;
use App\Http\Controllers\AdminReturnController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LibrarianDashboardController;
use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MemberRegistrationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\SabhaLogoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home', fn () => redirect()->route('home'))->name('home.redirect');
Route::post('/language', [LanguageController::class, 'update'])->name('language.switch');
Route::get('/books/search', [SearchController::class, 'publicBooks'])->name('public.books.search');
Route::get('/books/{book}', [SearchController::class, 'publicBook'])->name('public.books.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [MemberRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [MemberRegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});
Route::get('/registration-submitted', [MemberRegistrationController::class, 'submitted'])->name('register.submitted');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::view('/profile', 'account.profile')->name('account.profile');
    Route::get('/sabhas/{sabha}/logo', [SabhaLogoController::class, 'show'])->name('sabhas.logo');
    Route::middleware('role:SUPER_ADMIN|SABHA_ADMIN')->group(function () {
        Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    });
});

Route::middleware(['auth', 'role:MAIN_ADMIN|PRADESHIYA_SABHA_ADMIN', 'throttle:staff-actions', 'sabha.context'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->middleware('role:MAIN_ADMIN')->name('audit-logs.index');
    Route::get('/sabhas', [\App\Http\Controllers\AdminSabhaController::class, 'index'])->middleware('role:MAIN_ADMIN')->name('sabhas.index');
    Route::get('/sabhas/create', [\App\Http\Controllers\AdminSabhaController::class, 'create'])->middleware('role:MAIN_ADMIN')->name('sabhas.create');
    Route::post('/sabhas', [\App\Http\Controllers\AdminSabhaController::class, 'store'])->middleware('role:MAIN_ADMIN')->name('sabhas.store');
    Route::get('/sabhas/{sabha}', [\App\Http\Controllers\AdminSabhaController::class, 'show'])->middleware('role:MAIN_ADMIN')->name('sabhas.show');
    Route::put('/sabhas/{sabha}', [\App\Http\Controllers\AdminSabhaController::class, 'update'])->middleware('role:MAIN_ADMIN')->name('sabhas.update');
    Route::get('/sabhas/{sabha}/dashboard', [\App\Http\Controllers\AdminSabhaController::class, 'dashboard'])->middleware('role:MAIN_ADMIN')->name('sabhas.dashboard');
    Route::patch('/sabhas/{sabha}/status', [\App\Http\Controllers\AdminSabhaController::class, 'updateStatus'])->middleware('role:MAIN_ADMIN')->name('sabhas.status.update');
    Route::post('/sabhas/{sabha}/logo', [\App\Http\Controllers\AdminSabhaController::class, 'uploadLogo'])->middleware('role:MAIN_ADMIN')->name('sabhas.logo.upload');
    Route::delete('/sabhas/{sabha}/logo', [\App\Http\Controllers\AdminSabhaController::class, 'removeLogo'])->middleware('role:MAIN_ADMIN')->name('sabhas.logo.remove');
    Route::put('/sabhas/{sabha}/admin', [\App\Http\Controllers\AdminSabhaController::class, 'updateAdmin'])->middleware('role:MAIN_ADMIN')->name('sabhas.admin.update');
    Route::post('/sabhas/{sabha}/admin', [\App\Http\Controllers\AdminSabhaController::class, 'storeAdmin'])->middleware('role:MAIN_ADMIN')->name('sabhas.admin.store');
    Route::get('/search', [SearchController::class, 'globalSearch'])->name('search');
    Route::get('/books/search', [SearchController::class, 'books'])->name('books.search');
    Route::get('/members/search', [SearchController::class, 'members'])->name('members.search');
    Route::get('/borrowings/search', [SearchController::class, 'borrowings'])->name('borrowings.search');
    Route::get('/lost-books/search', [SearchController::class, 'lostBooks'])->name('lost-books.search');
    Route::get('/damaged-books/search', [SearchController::class, 'damagedBooks'])->name('damaged-books.search');

    Route::middleware('sabha.admin')->group(function () {
        Route::get('/member-applications', [AdminMemberApplicationController::class, 'index'])->name('member-applications.index');
        Route::get('/member-applications/{member}', [AdminMemberApplicationController::class, 'show'])->name('member-applications.show');
        Route::post('/member-applications/{member}/approve', [AdminMemberApplicationController::class, 'approve'])->name('member-applications.approve');
        Route::post('/member-applications/{member}/reject', [AdminMemberApplicationController::class, 'reject'])->name('member-applications.reject');
        Route::resource('members', AdminMemberController::class)->except(['index', 'show']);
        Route::post('/members/{member}/suspend', [AdminMemberController::class, 'suspend'])->name('members.suspend');
        Route::post('/members/{member}/reactivate', [AdminMemberController::class, 'reactivate'])->name('members.reactivate');
        Route::post('/members/{member}/renew', [AdminMemberController::class, 'renew'])->name('members.renew');
        Route::resource('authors', AdminAuthorController::class)->except(['index', 'show']);
        Route::resource('publishers', AdminPublisherController::class)->except(['index', 'show']);
        Route::resource('categories', AdminCategoryController::class)->except(['index', 'show']);
        Route::get('/books/isbn-lookup', [AdminBookController::class, 'lookupIsbn'])->name('books.isbn-lookup');
        Route::resource('books', AdminBookController::class)->except(['index', 'show']);
        Route::resource('book-copies', AdminBookCopyController::class)->except(['index', 'show']);
        Route::get('/issue-books', [AdminBorrowingController::class, 'create'])->name('issue-books');
        Route::get('/members/lookup', [AdminBorrowingController::class, 'lookupMember'])->name('members.lookup');
        Route::get('/books/lookup', [AdminBorrowingController::class, 'lookupBooks'])->name('books.lookup');
        Route::get('/borrowings/create', [AdminBorrowingController::class, 'create'])->name('borrowings.create');
        Route::post('/borrowings', [AdminBorrowingController::class, 'store'])->name('borrowings.store');
        Route::post('/borrowings/{borrowing}/return', [AdminBorrowingController::class, 'returnBook'])->name('borrowings.return');
        Route::post('/borrowings/{borrowing}/renew', [AdminBorrowingController::class, 'renew'])->name('borrowings.renew');
        Route::get('/fines/create', [AdminFineController::class, 'create'])->name('fines.create');
        Route::post('/fines', [AdminFineController::class, 'store'])->name('fines.store');
        Route::post('/fines/{fine}/pay', [AdminFineController::class, 'pay'])->name('fines.pay');
        Route::post('/fines/{fine}/waive', [AdminFineController::class, 'waive'])->name('fines.waive');
        Route::post('/payments/membership', [AdminPaymentController::class, 'storeMembership'])->name('payments.membership');
        Route::get('/lost-damaged/create-lost', [AdminLostDamagedController::class, 'createLost'])->name('lost-damaged.create-lost');
        Route::get('/lost-damaged/create-damaged', [AdminLostDamagedController::class, 'createDamaged'])->name('lost-damaged.create-damaged');
        Route::post('/lost-books', [AdminLostDamagedController::class, 'storeLost'])->name('lost-books.store');
        Route::post('/lost-books/{lostBook}/pay', [AdminLostDamagedController::class, 'payLost'])->name('lost-books.pay');
        Route::post('/damaged-books', [AdminLostDamagedController::class, 'storeDamaged'])->name('damaged-books.store');
        Route::post('/damaged-books/{damageRecord}/pay', [AdminLostDamagedController::class, 'payDamaged'])->name('damaged-books.pay');
    });

    Route::resource('members', AdminMemberController::class)->only(['index', 'show']);
    Route::resource('authors', AdminAuthorController::class)->only(['index', 'show']);
    Route::resource('publishers', AdminPublisherController::class)->only(['index', 'show']);
    Route::resource('categories', AdminCategoryController::class)->only(['index', 'show']);
    Route::resource('books', AdminBookController::class)->only(['index', 'show']);
    Route::resource('book-copies', AdminBookCopyController::class)->only(['index', 'show']);
    Route::get('/borrowings', [AdminBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::get('/renewals', [AdminRenewalController::class, 'index'])->name('renewals.index');
    Route::get('/overdue', function () {
        return view('admin.overdue');
    })->name('overdue');
    Route::get('/fines', [AdminFineController::class, 'index'])->name('fines.index');
    Route::get('/fines/{fine}', [AdminFineController::class, 'show'])->name('fines.show');
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::get('/lost-damaged', [AdminLostDamagedController::class, 'index'])->name('lost-damaged.index');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [AdminReportController::class, 'show'])->name('reports.show');
    Route::get('/settings', [AdminSettingController::class, 'index'])->middleware('role:SABHA_ADMIN')->name('settings.index');
    Route::put('/settings', [\App\Http\Controllers\AdminSabhaController::class, 'updateOwnSettings'])->middleware('role:SABHA_ADMIN')->name('settings.update');
});

Route::middleware(['auth', 'role:LIBRARIAN', 'throttle:staff-actions'])->prefix('librarian')->name('librarian.')->group(function () {
    Route::get('/dashboard', [LibrarianDashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'globalSearch'])->name('search');
    Route::get('/books/search', [SearchController::class, 'books'])->name('books.search');
    Route::get('/members/search', [SearchController::class, 'members'])->name('members.search');
    Route::get('/borrowings/search', [SearchController::class, 'borrowings'])->name('borrowings.search');
    Route::get('/lost-books/search', [SearchController::class, 'lostBooks'])->name('lost-books.search');
    Route::get('/damaged-books/search', [SearchController::class, 'damagedBooks'])->name('damaged-books.search');
    Route::get('/issue-books', [AdminBorrowingController::class, 'create'])->name('issue-books');
    Route::get('/members/lookup', [AdminBorrowingController::class, 'lookupMember'])->name('members.lookup');
    Route::get('/books/lookup', [AdminBorrowingController::class, 'lookupBooks'])->name('books.lookup');
    Route::get('/borrowings', [AdminBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::get('/renewals', [AdminRenewalController::class, 'index'])->name('renewals.index');
    Route::get('/borrowings/create', [AdminBorrowingController::class, 'create'])->name('borrowings.create');
    Route::post('/borrowings', [AdminBorrowingController::class, 'store'])->name('borrowings.store');
    Route::post('/borrowings/{borrowing}/return', [AdminBorrowingController::class, 'returnBook'])->name('borrowings.return');
    Route::post('/borrowings/{borrowing}/renew', [AdminBorrowingController::class, 'renew'])->name('borrowings.renew');
    Route::resource('members', AdminMemberController::class)->except(['create', 'store']);
    Route::post('/members/{member}/suspend', [AdminMemberController::class, 'suspend'])->name('members.suspend');
    Route::post('/members/{member}/reactivate', [AdminMemberController::class, 'reactivate'])->name('members.reactivate');
    Route::post('/members/{member}/renew', [AdminMemberController::class, 'renew'])->name('members.renew');
    Route::get('/fines', [AdminFineController::class, 'index'])->name('fines.index');
    Route::get('/fines/create', [AdminFineController::class, 'create'])->name('fines.create');
    Route::get('/fines/{fine}', [AdminFineController::class, 'show'])->name('fines.show');
    Route::post('/fines', [AdminFineController::class, 'store'])->name('fines.store');
    Route::post('/fines/{fine}/pay', [AdminFineController::class, 'pay'])->name('fines.pay');
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/membership', [AdminPaymentController::class, 'storeMembership'])->name('payments.membership');
    Route::get('/lost-damaged', [AdminLostDamagedController::class, 'index'])->name('lost-damaged.index');
    Route::get('/lost-damaged/create-lost', [AdminLostDamagedController::class, 'createLost'])->name('lost-damaged.create-lost');
    Route::get('/lost-damaged/create-damaged', [AdminLostDamagedController::class, 'createDamaged'])->name('lost-damaged.create-damaged');
    Route::post('/lost-books', [AdminLostDamagedController::class, 'storeLost'])->name('lost-books.store');
    Route::post('/lost-books/{lostBook}/pay', [AdminLostDamagedController::class, 'payLost'])->name('lost-books.pay');
    Route::post('/damaged-books', [AdminLostDamagedController::class, 'storeDamaged'])->name('damaged-books.store');
    Route::post('/damaged-books/{damageRecord}/pay', [AdminLostDamagedController::class, 'payDamaged'])->name('damaged-books.pay');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [AdminReportController::class, 'show'])->name('reports.show');
    Route::get('/books', function () {
        return redirect()->route('admin.books.index');
    });
});

Route::middleware(['auth', 'role:MEMBER', 'sabha.context'])->prefix('member')->name('member.')->group(function () {
    Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'globalSearch'])->name('search');
    Route::get('/books/search', [SearchController::class, 'books'])->name('books.search');
    Route::get('/books/{book}', [MemberPortalController::class, 'book'])->name('books.show');
    Route::get('/alerts', [MemberPortalController::class, 'alerts'])->name('alerts');
    Route::get('/password', [AccountPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [AccountPasswordController::class, 'update'])->name('password.update');
    Route::get('/membership', [MemberProfileController::class, 'show'])->name('membership');
    Route::get('/payments', [MemberProfileController::class, 'payments'])->name('payments');
    Route::get('/fines', [MemberProfileController::class, 'fines'])->name('fines');
    Route::get('/lost-books', [MemberProfileController::class, 'lostBooks'])->name('lost-books');
    Route::get('/damaged-books', [MemberProfileController::class, 'damagedBooks'])->name('damaged-books');
    Route::get('/outstanding-balance', [MemberProfileController::class, 'outstandingBalance'])->name('outstanding-balance');
    Route::get('/borrowings', [MemberProfileController::class, 'borrowings'])->name('borrowings');
    Route::post('/borrowings/{borrowing}/renew', [MemberPortalController::class, 'renew'])->name('renewals.store');
    Route::get('/books', function () {
        return redirect()->route('member.books.search');
    })->name('books');
    Route::get('/profile', [MemberProfileController::class, 'show'])->name('profile');
});
