<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return view('app.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:150',
            'locale' => 'required|in:uz,ru',
        ]);
        $request->user()->update($data);
        session(['locale' => $data['locale']]);

        return back()->with('success', 'Profil saqlandi');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => "Joriy parol noto'g'ri", 'password.confirmed' => 'Parollar bir xil emas']);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Parol yangilandi');
    }
}
