<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'p_wx_media';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
}
