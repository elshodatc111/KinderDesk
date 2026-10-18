<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;

/** Yo'riqnoma: joriy tildagi to'liq qo'llanma. */
class GuideController extends Controller
{
    public function index()
    {
        $lang = app()->getLocale() === 'uz' ? 'uz' : 'ru';

        return view("app.guide.$lang", ['t' => tenant()]);
    }
}
