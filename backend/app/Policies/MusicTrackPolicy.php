<?php

namespace App\Policies;

use App\Admin;
use App\MusicSetting;
use App\MusicTrack;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MusicTrackPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user or guest can view the track.
     */
    public function view(?User $user, MusicTrack $track): bool
    {
        if ($track->isApproved() && !$track->isSuspended()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        // The owning seller can view their own track in any state
        return $track->isOwnedBySeller() && (int) $track->seller_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create tracks.
     */
    public function create(User $user): bool
    {
        // Only active verified providers/sellers can create tracks, provided seller submissions are enabled
        return $user->isProvider() && (int) $user->user_status === 1 && MusicSetting::areSellerSubmissionsEnabled();
    }

    /**
     * Determine whether the user can update the track.
     * Enforces seller ownership isolation and server-side identity derivation.
     */
    public function update(User $user, MusicTrack $track): bool
    {
        // Sellers cannot update platform/admin-owned tracks
        if (!$track->isOwnedBySeller()) {
            return false;
        }

        // Must strictly be the owner
        if ((int) $track->seller_id !== (int) $user->id) {
            return false;
        }

        // Suspended tracks cannot be edited by sellers until resolved
        if ($track->isSuspended()) {
            return false;
        }

        // Approved tracks require admin review to modify core audio/metadata
        return in_array($track->status, [MusicTrack::STATUS_DRAFT, MusicTrack::STATUS_REJECTED], true);
    }

    /**
     * Determine whether the user can delete the track.
     */
    public function delete(User $user, MusicTrack $track): bool
    {
        if (!$track->isOwnedBySeller() || (int) $track->seller_id !== (int) $user->id) {
            return false;
        }

        // Cannot delete if active paid orders exist (must preserve financial reconciliation)
        if ($track->orders()->whereIn('payment_status', ['completed'])->exists()) {
            return false;
        }

        return in_array($track->status, [MusicTrack::STATUS_DRAFT, MusicTrack::STATUS_REJECTED], true);
    }

    /**
     * Admin authorization check for moderation and platform management.
     */
    public function moderateAdmin(Admin $admin): bool
    {
        return !empty($admin->id);
    }
}
