<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'nullable|email|max:150', 'locale' => 'required|in:uz,ru']);
        $request->user()->update($data);
        session(['locale' => $data['locale']]);

        return back()->with('success', 'Profil saqlandi');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], ['current_password.current_password' => "Joriy parol noto'g'ri", 'password.confirmed' => 'Parollar bir xil emas']);
        $request->user()->update(['password' => $data['password']]);
        AuditLog::record('admin.password_changed', $request->user());

        return back()->with('success', 'Parol yangilandi');
    }
}
