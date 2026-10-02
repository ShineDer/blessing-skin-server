<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationCampaignRun extends Model
{
    protected $guarded = [];
    protected $casts = ['audience_snapshot' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function campaign() { return $this->belongsTo(NotificationCampaign::class, 'campaign_id'); }
    public function deliveries() { return $this->hasMany(NotificationDelivery::class, 'campaign_run_id'); }
}
