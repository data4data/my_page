<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 11+ ships this base class empty. The trait is what makes
    // $this->authorize() available, so the ownership rules can live in
    // app/Policies instead of being repeated in every controller.
    use AuthorizesRequests;
}
