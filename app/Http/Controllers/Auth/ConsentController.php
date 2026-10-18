<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\ConsentService;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function show(Request $request, ConsentService $consent)
    {
        if (! $consent->needs($request->user())) {
            return redirect($this->home($request));
        }

        return view('auth.consent', ['pages' => \App\Support\Site\Legal::PAGES]);
    }

    public function accept(Request $request, ConsentService $consent)
    {
        $request->validate(['agree' => 'accepted'], ['agree.accepted' => 'Davom etish uchun shartlarga rozilik bering']);
        $consent->record($request->user(), $request);

        return redirect()->intended($this->home($request));
    }

    private function home(Request $request): string
    {
        return $request->user()->is_super_admin ? route('admin.dashboard') : route('app.dashboard');
    }
}
