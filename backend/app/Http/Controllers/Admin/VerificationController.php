<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function pending(): JsonResponse
    {
        $verifications = UserVerification::where('status', 'pending')
            ->with('user:id,nickname,gender,avatar_url')
            ->orderBy('submitted_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $verifications]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reject_reason' => 'required_if:action,reject|string|max:500',
        ]);

        $verification = UserVerification::findOrFail($id);
        $verification->update([
            'status' => $request->action === 'approve' ? 'approved' : 'rejected',
            'reject_reason' => $request->action === 'reject' ? $request->reject_reason : null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        if ($request->action === 'approve' && $verification->user) {
            // Upgrade user level to 1.5 or 2 based on verification type
            $newLevel = $verification->type === 'photo' ? 1.5 : 2;
            \DB::table('users')->where('id', $verification->user_id)
                ->where('membership_level', '<', $newLevel)
                ->update(['membership_level' => $newLevel]);
        }

        return response()->json(['success' => true, 'message' => $request->action === 'approve' ? '驗證已通過' : '驗證已拒絕']);
    }
}
