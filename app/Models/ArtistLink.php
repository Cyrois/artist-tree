<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistLink extends Model
{
    use HasFactory;

    /**
     * Review status values for channel links.
     */
    public const REVIEW_STATUS_PUBLIC_ADDED = 'public_added';
    public const REVIEW_STATUS_ADMIN_ADDED = 'admin_added';
    public const REVIEW_STATUS_PENDING_APPROVAL = 'pending_approval';
    public const REVIEW_STATUS_APPROVED = 'approved';

    protected $fillable = [
        'artist_id',
        'platform',
        'url',
        'vote_score',
        'review_status',
        'vevo_checked_at',
    ];

    protected $casts = [
        'platform' => \App\Enums\SocialPlatform::class,
        'vevo_checked_at' => 'datetime',
    ];

    protected $attributes = [
        'review_status' => self::REVIEW_STATUS_PUBLIC_ADDED,
    ];

    public function artist()
    {
        return $this->belongsTo(Artist::class);
    }

    public function votes()
    {
        return $this->hasMany(ArtistLinkVote::class);
    }

    /**
     * Check if this link needs VEVO detection check.
     * Returns true if never checked or checked more than 7 days ago.
     */
    public function needsVevoCheck(): bool
    {
        if (!$this->vevo_checked_at) {
            return true;
        }

        return $this->vevo_checked_at->lt(now()->subDays(7));
    }

    /**
     * Mark this link as checked for VEVO detection.
     */
    public function markVevoChecked(): void
    {
        $this->update(['vevo_checked_at' => now()]);
    }

    /**
     * Mark this link as pending approval (used after automatic VEVO replacement).
     */
    public function markPendingApproval(): void
    {
        $this->update(['review_status' => self::REVIEW_STATUS_PENDING_APPROVAL]);
    }

    /**
     * Check if this link is a YouTube link.
     */
    public function isYouTubeLink(): bool
    {
        return $this->platform === \App\Enums\SocialPlatform::YouTube;
    }
}