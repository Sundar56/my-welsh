<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'App\Api\Admin\Modules\Customers\Controllers',
    'middleware' => [
        \App\Http\Middleware\CustomTokenValidation::class,
    ],
], function () {
    Route::post('/admin/addcustomers', 'CustomersController@addCustomers')->name('admin.customers.create');
    Route::get('/admin/getcustomers', 'CustomersController@customersList')->name('admin.customers.get');
    Route::post('/admin/viewcustomer', 'CustomersController@viewCustomer')->name('admin.customers.view');
    Route::post('/admin/editcustomer', 'CustomersController@editCustomer')->name('admin.customers.edit');
    Route::get('/admin/subscriptiontypes', 'CustomersController@subscriptionTypeList')->name('admin.customers.typelist');
    Route::post('/admin/activation', 'CustomersController@activateCustomer')->name('admin.customers.activate');
    Route::get('/admin/getbillingdata', 'CustomersController@getBillingEmails')->name('admin.customers.getbilling');
});
