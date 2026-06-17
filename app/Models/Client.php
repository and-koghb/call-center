<?php

namespace App\Models;

use App\Traits\HighLoadSoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, HighLoadSoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'deleted_at',
    ];
}
