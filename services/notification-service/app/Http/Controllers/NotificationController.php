<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/notifications - mes notifications (JWT) */
    public function index(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        $admin = in_array('*', $perms, true);
        $q = DB::table('notifications')->orderByDesc('envoye_le');
        // Sauf admin, on ne voit que SES notifications (anti-IDOR)
        $uid = $admin ? ($request->query('utilisateur_id') ?? null) : ($claims['sub'] ?? null);
        if ($uid) $q->where('utilisateur_id', $uid);
        if ($request->has('non_lues')) $q->where('lu', false);
        if ($t = $request->query('type')) $q->where('type', $t);
        return $this->ok($q->paginate(30));
    }

    public function unreadCount(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        return $this->ok(['non_lues' => DB::table('notifications')->where('utilisateur_id', $claims['sub'] ?? '')->where('lu', false)->count()]);
    }

    /** POST /api/v1/notifications - creation INTERNE (services via SERVICE_TOKEN) */
    public function store(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        $isService = ($claims['sub'] ?? '') === 'service' || in_array('*', $perms, true);
        if (! $isService) {
            return $this->error('FORBIDDEN', 'Endpoint reserve aux services internes.', 403);
        }
        $d = $request->validate([
            'utilisateur_id' => 'nullable|uuid',
            'structure_id' => 'nullable|uuid',
            'type' => 'required|string|max:50',
            'titre' => 'nullable|string|max:200',
            'message' => 'required|string|max:2000',
            'entite_type' => 'nullable|string|max:50',
            'entite_id' => 'nullable|uuid',
        ]);
        $id = (string) Str::uuid();
        DB::table('notifications')->insert($d + [
            'id' => $id, 'titre' => $d['titre'] ?? $d['type'],
            'envoye_le' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $this->created(DB::table('notifications')->find($id));
    }

    /** POST /api/v1/notifications/{id}/read */
    public function markRead(Request $request, string $id)
    {
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        $admin = in_array('*', $perms, true);
        $q = DB::table('notifications')->where('id', $id);
        if (! $admin) $q->where('utilisateur_id', $claims['sub'] ?? '');
        $n = $q->update(['lu' => true, 'updated_at' => now()]);
        if (! $n) return $this->error('NOT_FOUND', 'Notification introuvable.', 404);
        return $this->ok(['lu' => true]);
    }

    /** POST /api/v1/audit - journal d'audit (INTERNE, services via SERVICE_TOKEN) */
    public function audit(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        $isService = ($claims['sub'] ?? '') === 'service' || in_array('*', $perms, true);
        if (! $isService) {
            return $this->error('FORBIDDEN', 'Endpoint reserve aux services internes.', 403);
        }
        $d = $request->validate([
            'utilisateur_id' => 'nullable|uuid',
            'service_source' => 'required|string|max:50',
            'action' => 'required|string|max:100',
            'entite' => 'required|string|max:50',
            'entite_id' => 'nullable|uuid',
            'avant' => 'nullable|array',
            'apres' => 'nullable|array',
            'ip' => 'nullable|string|max:45',
        ]);
        DB::table('audit_logs')->insert($d + [
            'id' => (string) Str::uuid(),
            'avant' => isset($d['avant']) ? json_encode($d['avant']) : null,
            'apres' => isset($d['apres']) ? json_encode($d['apres']) : null,
            'cree_le' => now(),
        ]);
        return $this->created(['ok' => true]);
    }

    /** POST /api/v1/notifications/read-all */
    public function markAllRead(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        DB::table('notifications')->where('utilisateur_id', $claims['sub'] ?? '')->where('lu', false)->update(['lu' => true, 'updated_at' => now()]);
        return $this->ok(['ok' => true]);
    }
}
