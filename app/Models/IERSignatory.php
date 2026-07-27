<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IERSignatory extends Model
{
    protected $table = 'ier_signatories';

    protected $fillable = ['name', 'position'];
}