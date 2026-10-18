<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $t = tenant();
        $tab = $request->query('tab', 'general');

        $groups = [];
        foreach (config('kinderdesk.settings') as $gKey => $group) {
            if (isset($group['feature']) && ! $t->hasFeature($group['feature'])) {
                continue;
            }
            $items = [];
            foreach ($group['items'] as $key => $def) {
                if (isset($def['feature']) && ! $t->hasFeature($def['feature'])) {
                    continue;
                }
                if (isset($def['option_features'])) {
                    foreach ($def['option_features'] as $opt => $feat) {
                        if (! $t->hasFeature($feat) && $t->setting($key) !== $opt) {
                            $def['locked'][$opt] = $feat;
                        }
                    }
                }
                $def['value'] = $t->setting($key);
                $items[$key] = $def;
            }
            $groups[$gKey] = ['title' => $group['title'], 'items' => $items];
        }

        return view('app.settings.index', [
            'tab' => $tab,
            'groups' => $groups,
            'allowed' => $t->allowedFeatures(),
            'enabled' => $t->featureMap(),
            'features' => config('kinderdesk.features'),
            'roles' => Role::where('tenant_id', $t->id)->withCount(['users', 'employees'])->orderByDesc('is_system')->orderBy('id')->get(),
            'permissions' => config('kinderdesk.permissions'),
            'users' => User::where('tenant_id', $t->id)->with('role')->orderByDesc('is_owner')->orderBy('name')->get(),
            
        ]);
    }

    public function update(Request $request)
    {
        $t = tenant();
        $defs = SettingsRegistry::definitions();
        foreach ($request->input('s', []) as $key => $value) {
            $def = $defs[$key] ?? null;
            if (! $def || (isset($def['feature']) && ! $t->hasFeature($def['feature']))) {
                continue;
            }
            // Nashrda yo'q variantni tanlashga yo'l qo'ymaymiz
            if ($def['type'] === 'select' && isset($def['option_features'][$value]) && ! $t->hasFeature($def['option_features'][$value])) {
                continue;
            }
            $t->setSetting($key, $value);
        }
        // Belgilanmagan checkboxlar (bool) false bo'ladi
        foreach ($request->input('b', []) as $key => $_) {
            $def = $defs[$key] ?? null;
            if ($def && $def['type'] === 'bool' && ! array_key_exists($key, $request->input('s', []))) {
                $t->setSetting($key, false);
            }
        }
        $t->forgetCaches();
        AuditLog::record('settings.updated', null, ['group' => $request->input('group')]);

        return back()->with('success', 'Sozlamalar saqlandi');
    }

    public function modules(Request $request)
    {
        $t = tenant();
        $on = (array) $request->input('on', []);
        $hidden = [];
        foreach ($t->allowedFeatures() as $key => $allowed) {
            if ($allowed && empty(config("kinderdesk.features.$key.core")) && ! in_array($key, $on, true)) {
                $hidden[] = $key;
            }
        }
        $t->setSetting('modules.disabled', $hidden);
        $t->forgetCaches();
        AuditLog::record('modules.updated', null, ['hidden' => $hidden]);

        return redirect()->route('app.settings.index', ['tab' => 'modules'])->with('success', "Bo'limlar yangilandi");
    }

    public function organization(Request $request)
    {
        $t = tenant();
        $data = $request->validate([
            'name' => 'required|string|max:120', 'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200', 'logo' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('logo')) {
            if ($t->logo_path) {
                Storage::disk('public')->delete($t->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);
        if (! empty($data['phone'])) {
            $data['phone'] = normalize_phone($data['phone']) ?: $t->phone;
        }
        $t->update($data);

        return redirect()->route('app.settings.index', ['tab' => 'org'])->with('success', "Bog'cha ma'lumotlari saqlandi");
    }

    /* ---------- Lavozimlar (direktor yaratadi, ruxsatlar checkbox bilan) ---------- */

    public function roleCreate()
    {
        return view('app.settings.role', ['role' => new Role(['permissions' => []]), 'groups' => $this->permissionGroups()]);
    }

    public function roleEdit(Role $role)
    {
        abort_unless($role->tenant_id === tenant()->id, 404);

        return view('app.settings.role', ['role' => $role, 'groups' => $this->permissionGroups()]);
    }

    public function roleStore(Request $request)
    {
        $data = $this->roleData($request);
        Role::create([...$data, 'tenant_id' => tenant()->id, 'key' => 'custom_'.str()->random(6), 'is_system' => false]);
        AuditLog::record('role.created', null, ['name' => $data['name']]);

        return redirect()->route('app.settings.index', ['tab' => 'roles'])->with('success', "Lavozim qo'shildi");
    }

    public function roleUpdate(Request $request, Role $role)
    {
        abort_unless($role->tenant_id === tenant()->id, 404);
        abort_if($role->key === 'director', 403);
        $role->update($this->roleData($request));
        AuditLog::record('role.updated', null, ['role' => $role->id]);

        return redirect()->route('app.settings.index', ['tab' => 'roles'])->with('success', 'Lavozim saqlandi');
    }

    public function roleDestroy(Role $role)
    {
        abort_unless($role->tenant_id === tenant()->id, 404);
        abort_if($role->key === 'director', 403);
        if ($role->users()->exists() || $role->employees()->exists()) {
            return back()->with('error', "Bu lavozimda xodimlar bor. Avval ularni boshqa lavozimga o'tkazing.");
        }
        $role->delete();

        return back()->with('success', "Lavozim o'chirildi");
    }

    private function permissionGroups(): array
    {
        $t = tenant();
        $out = [];
        foreach (config('kinderdesk.permission_groups') as $title => $keys) {
            $keys = array_values(array_filter($keys, function ($k) use ($t) {
                return ! (str_starts_with($k, 'clubs.') && ! $t->hasFeature('clubs')) && ! (str_starts_with($k, 'desk.') && ! $t->hasFeature('desk'));
            }));
            if ($keys) {
                $out[$title] = $keys;
            }
        }

        return $out;
    }

    private function roleData(Request $request): array
    {
        $data = $request->validate(['name' => 'required|string|max:60', 'permissions' => 'array', 'permissions.*' => 'string']);

        return ['name' => $data['name'], 'permissions' => array_values(array_intersect($data['permissions'] ?? [], array_keys(config('kinderdesk.permissions'))))];
    }

    /* ---------- Foydalanuvchilar ---------- */

    public function userStore(Request $request)
    {
        $t = tenant();
        $data = $request->validate([
            'name' => 'required|string|max:120', 'phone' => 'required|string', 'role_id' => 'required|integer',
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);
        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => "Telefon raqam noto'g'ri"]);
        }
        if (User::where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'Bu raqam bilan foydalanuvchi mavjud']);
        }
        $role = Role::where('tenant_id', $t->id)->findOrFail($data['role_id']);
        if ($role->key === 'director') {
            return back()->with('error', "Direktor rolini boshqaga berib bo'lmaydi");
        }
        User::create(['tenant_id' => $t->id, 'role_id' => $role->id, 'name' => $data['name'], 'phone' => $phone,
            'password' => $data['password'], 'status' => 'active', 'phone_verified_at' => now(), 'locale' => app()->getLocale()]);
        AuditLog::record('user.created', null, ['phone' => $phone]);

        return redirect()->route('app.settings.index', ['tab' => 'users'])->with('success', "Foydalanuvchi qo'shildi");
    }

    public function userUpdate(Request $request, User $user)
    {
        $t = tenant();
        abort_unless($user->tenant_id === $t->id, 404);
        abort_if($user->is_owner, 403);
        $data = $request->validate(['role_id' => 'required|integer', 'status' => 'required|in:active,blocked', 'password' => ['nullable', Password::min(8)->letters()->numbers()]]);
        $role = Role::where('tenant_id', $t->id)->findOrFail($data['role_id']);
        abort_if($role->key === 'director', 403);
        $user->role_id = $role->id;
        $user->status = $data['status'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('app.settings.index', ['tab' => 'users'])->with('success', 'Foydalanuvchi saqlandi');
    }

    public function userDestroy(User $user)
    {
        abort_unless($user->tenant_id === tenant()->id, 404);
        abort_if($user->is_owner || $user->id === auth()->id(), 403);
        $user->delete();

        return back()->with('success', "Foydalanuvchi o'chirildi");
    }
}
