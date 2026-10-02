<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    protected $guarded = [];
    protected $casts = ['audience_snapshot' => 'array', 'popup_enabled' => 'bool', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    public function runs() { return $this->hasMany(NotificationCampaignRun::class, 'campaign_id'); }
}
