<?php

namespace App\Http\Controllers\Api;

use App\Models\Membership;
use App\Models\MembershipInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class TeamController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $members = Membership::where('tenant_id', $tenantId)
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->select('memberships.id', 'memberships.user_id', 'memberships.role', 'memberships.status', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        $invitations = MembershipInvitation::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->select('id', 'email', 'role', 'status', 'expires_at', 'created_at')
            ->latest()
            ->get();

        return response()->json(['members' => $members, 'invitations' => $invitations]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->attributes->get('membership_role') === 'agent', 403, 'Sem permissão para gerir a equipa.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'in:admin,agent'],
        ]);

        $email = mb_strtolower($data['email']);
        $tenantId = $this->tenantId($request);
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $membership = Membership::updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $existingUser->id],
                ['role' => $data['role'], 'status' => 'active']
            );
            $this->audit($request, 'member_added', 'membership', $membership->id, ['user_id' => $existingUser->id, 'role' => $data['role']]);

            return response()->json(['membership' => $membership, 'invitation' => null], 201);
        }

        MembershipInvitation::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $plainToken = Str::random(64);
        $invitation = MembershipInvitation::create([
            'tenant_id' => $tenantId,
            'invited_by' => $request->user()->id,
            'email' => $email,
            'role' => $data['role'],
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
        ]);

        $tenantName = Tenant::whereKey($tenantId)->value('name') ?? 'Vitaleevo';
        Notification::route('mail', $email)->notify(
            new TeamInvitationNotification($tenantName, $plainToken, $data['role'])
        );

        $this->audit($request, 'member_invited', 'membership_invitation', $invitation->id, ['email' => $email, 'role' => $data['role']]);

        return response()->json([
            'membership' => null,
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'token' => $plainToken,
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_if($request->attributes->get('membership_role') === 'agent', 403, 'Sem permissão para gerir a equipa.');

        $membership = Membership::where('tenant_id', $this->tenantId($request))->findOrFail($id);
        abort_if($membership->user_id === $request->user()->id, 422, 'Não pode alterar a sua própria membership nesta operação.');

        $membership->update($request->validate([
            'role' => ['sometimes', 'in:admin,agent'],
            'status' => ['sometimes', 'in:active,suspended'],
        ]));

        $this->audit($request, 'member_updated', 'membership', $membership->id);

        return response()->json($membership);
    }
}
