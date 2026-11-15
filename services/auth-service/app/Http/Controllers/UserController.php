<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/auth/users */
    public function index(Request $request)
    {
        $query = User::query()->with('roles:id,code,libelle');

        if ($s = $request->query('search')) {
            $query->where(fn ($q) => $q->where('nom', 'ilike', "%$s%")
                ->orWhere('prenom', 'ilike', "%$s%")
                ->orWhere('email', 'ilike', "%$s%"));
        }
        if ($structure = $request->query('structure_id')) {
            $query->where('structure_id', $structure);
        }
        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $users = $query->latest()->paginate($perPage);
        return $this->ok($users->items(), 200, [
            'total' => $users->total(),
            'page' => $users->currentPage(),
            'per_page' => $users->perPage(),
        ]);
    }

    /** POST /api/v1/auth/users */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'structure_id' => ['nullable', 'uuid'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        $data['role_ids'] = $this->filtrerRolesAutorises($request, $data['role_ids'] ?? []);

        $password = Str::password(14, true, true, false, false);
        $user = User::create([
            ...$data,
            'mot_de_passe_hash' => Hash::make($password),
            'must_change_password_at' => now(),
        ]);
        if (! empty($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        return $this->created([
            'user' => $user->load('roles:id,code,libelle'),
            'temporary_password' => $password, // a transmettre a l'utilisateur (canal securise)
        ]);
    }

    /** GET /api/v1/auth/users/{id} */
    public function show(string $id)
    {
        $user = User::with('roles.permissions')->findOrFail($id);
        return $this->ok($user);
    }

    /** PUT /api/v1/auth/users/{id} */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:100'],
            'prenom' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'structure_id' => ['nullable', 'uuid'],
            'statut' => ['sometimes', 'in:actif,suspendu,desactive'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        if (isset($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }
        if (array_key_exists('role_ids', $data)) {
            $data['role_ids'] = $this->filtrerRolesAutorises($request, $data['role_ids'] ?? []);
        }

        // Garde-fous : pas d'auto-desactivation ni de perte du dernier admin
        $claims = $request->attributes->get('jwt', []);
        $isSelf = ($claims['sub'] ?? null) === $id;
        $perms = (array) ($claims['permissions'] ?? []);
        $admin = in_array('*', $perms, true);
        if ($isSelf && ($data['statut'] ?? null) === 'desactive') {
            return $this->error('SELF_LOCKOUT', 'Vous ne pouvez pas desactiver votre propre compte.', 422);
        }
        if (! $admin && $user->roles->contains('code', 'admin') && ($data['statut'] ?? null) !== 'actif') {
            return $this->error('FORBIDDEN', 'Seul un administrateur peut modifier le statut d\'un administrateur.', 403);
        }

        if (! empty($data['password'])) {
            $data['mot_de_passe_hash'] = Hash::make($data['password']);
            $data['must_change_password_at'] = null; // mot de passe defini par un tiers -> changement requis
        }
        unset($data['password'], $data['password_confirmation']);
        $user->update(collect($data)->except('role_ids')->toArray());
        if (array_key_exists('role_ids', $data)) {
            $user->roles()->sync($data['role_ids'] ?? []);
        }
        return $this->ok($user->load('roles:id,code,libelle'));
    }

    /** DELETE /api/v1/auth/users/{id} (soft delete) */
    public function destroy(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $claims = $request->attributes->get('jwt', []);
        if (($claims['sub'] ?? null) === $id) {
            return $this->error('SELF_LOCKOUT', 'Vous ne pouvez pas supprimer votre propre compte.', 422);
        }
        $perms = (array) ($claims['permissions'] ?? []);
        if (! in_array('*', $perms, true) && $user->roles->contains('code', 'admin')) {
            return $this->error('FORBIDDEN', 'Seul un administrateur peut supprimer un administrateur.', 403);
        }
        $user->delete();
        return $this->ok(['message' => 'Utilisateur supprime.']);
    }

    /**
     * Un acteur sans '*' ne peut attribuer que des roles dont les permissions
     * sont incluses dans les siennes (anti elevation de privileges).
     */
    private function filtrerRolesAutorises(Request $request, array $roleIds): array
    {
        $perms = (array) ($request->attributes->get('jwt')['permissions'] ?? []);
        if (in_array('*', $perms, true) || empty($roleIds)) {
            return $roleIds;
        }
        $roles = \App\Models\Role::with('permissions')->whereIn('id', $roleIds)->get();
        return $roles->filter(function ($role) use ($perms) {
            $rolePerms = $role->permissions->pluck('code')->all();
            // Le role admin (permissions *) est refuse aux non-admins
            if (in_array('*', $rolePerms, true)) return false;
            return count(array_diff($rolePerms, $perms)) === 0;
        })->pluck('id')->all();
    }
}
