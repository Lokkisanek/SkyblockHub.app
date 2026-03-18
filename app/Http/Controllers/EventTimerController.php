<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class EventTimerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('EventTimer/Index');
    }
}
