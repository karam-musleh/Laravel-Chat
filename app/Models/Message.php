<?php

namespace App\Models;

use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    //
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
    ];



    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
