<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Teacher\Modules\Subscription\Controllers',
], function () {
    Route::get('/trailresourcelist', 'SubscriptionController@trailResourceList')->name('teacher.trail.getlist');
    Route::post('/updateusertrail', 'SubscriptionController@updateTrailData')->name('teacher.trail.update');

    Route::get('/subscriptionlist/{lang?}', 'SubscriptionController@getSubscriptionList')->name('teacher.subscription.getlist');
});
