<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    protected $guarded = [];
    protected $casts = [
        'audience_snapshot' => 'array',
        'popup_enabled' => 'bool',
        'publicity_enabled' => 'bool',
        'public_days' => 'integer',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $appends = ['is_publicity_active'];

    public function getIsPublicityActiveAttribute(): bool
    {
        return $this->status === 'published'
            && $this->popup_enabled
            && $this->publicity_enabled
            && $this->public_days > 0
            && $this->expires_at?->isFuture();
    }

    public function runs() { return $this->hasMany(NotificationCampaignRun::class, 'campaign_id'); }
}
