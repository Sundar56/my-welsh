<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'App\Api\Teacher\Modules\Signup\Controllers'], function () {
    Route::post('/signup', 'TeacherSignupController@userSignup')->name('teacher.signup');

    // Stripe payment intent API
    Route::post('/stripe/paymentintent', 'StripeController@storePaymentIntent')->name('stripe.customer.paymentintent');

    // Stripe webhook API
    Route::post('/webhook/callback', 'StripeController@stripeWebhook')->name('webhook.payments');
});
