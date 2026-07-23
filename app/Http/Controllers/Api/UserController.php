<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::with('roles:id,name,slug')->orderBy('name')->orderBy('id');
        if ($search = trim($validated['search'] ?? '')) {
            $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }

    public function roles()
    {
        return response()->json(Role::query()->orderBy('name')->get(['id', 'name', 'slug']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:10|max:255|confirmed',
            'role_slugs' => 'required|array|min:1',
            'role_slugs.*' => 'required|string|distinct|exists:roles,slug',
            'active' => 'sometimes|boolean',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => Str::lower(trim($validated['email'])),
                'password' => $validated['password'],
                'active' => $validated['active'] ?? true,
            ]);
            $user->roles()->sync(Role::whereIn('slug', $validated['role_slugs'])->pluck('id'));

            return $user;
        });

        return response()->json($user->load('roles:id,name,slug'), 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:10|max:255|confirmed',
            'role_slugs' => 'sometimes|required|array|min:1',
            'role_slugs.*' => 'required|string|distinct|exists:roles,slug',
            'active' => 'sometimes|boolean',
        ]);

        if ($request->user()->is($user) && array_key_exists('active', $validated) && ! $validated['active']) {
            throw ValidationException::withMessages(['active' => 'No puedes desactivar tu propia cuenta.']);
        }

        $removesOwner = $user->hasRole('owner')
            && ((array_key_exists('role_slugs', $validated) && ! in_array('owner', $validated['role_slugs'], true))
                || (array_key_exists('active', $validated) && ! $validated['active']));
        if ($removesOwner && User::where('active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))->count() <= 1) {
            throw ValidationException::withMessages(['role_slugs' => 'Debe permanecer al menos un propietario activo.']);
        }

        DB::transaction(function () use ($validated, $user) {
            $attributes = [];
            foreach (['name', 'active'] as $key) {
                if (array_key_exists($key, $validated)) $attributes[$key] = is_string($validated[$key]) ? trim($validated[$key]) : $validated[$key];
            }
            if (array_key_exists('email', $validated)) $attributes['email'] = Str::lower(trim($validated['email']));
            if (! empty($validated['password'])) $attributes['password'] = $validated['password'];
            $user->update($attributes);

            if (array_key_exists('role_slugs', $validated)) {
                $user->roles()->sync(Role::whereIn('slug', $validated['role_slugs'])->pluck('id'));
            }
            if (array_key_exists('active', $validated) && ! $validated['active']) {
                $user->tokens()->delete();
            }
        });

        return response()->json($user->fresh()->load('roles:id,name,slug'));
    }
}
