<?php

namespace Modules\Core\Webhooks;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_id', 'event', 'status', 'http_code', 'error'];
}
