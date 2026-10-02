<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = [];
    protected $casts = ['eligible' => 'bool', 'delivered' => 'bool', 'popup_seen' => 'bool', 'visible' => 'bool', 'read_at' => 'datetime', 'deleted_at' => 'datetime'];
    public function run() { return $this->belongsTo(NotificationCampaignRun::class, 'campaign_run_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id', 'uid'); }
}
