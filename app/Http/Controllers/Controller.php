<?php

declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Base controller.
 *
 * In Laravel 11 controllers no longer extend a base class by default.
 * We keep this abstract class so sub-controllers can share the
 * ApiResponse trait from a single inheritance point.
 */
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
