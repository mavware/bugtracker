<?php

namespace App\Enums;

/**
 * A thing a role may do. Gates are registered from these cases in
 * AppServiceProvider, so `can:` middleware, @can and Gate::allows all take the
 * same string value.
 */
enum Permission: string
{
    /** Open the /admin panel: every account, session and customer on the site. */
    case AccessAdmin = 'access-admin';

    /** Keep a customer list and file nights under a property. */
    case ManageCustomers = 'manage-customers';
}
