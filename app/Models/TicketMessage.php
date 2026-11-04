<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'support_ticket_id', 'author_type', 'author_name', 'body'];
}
