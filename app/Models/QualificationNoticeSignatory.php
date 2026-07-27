<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualificationNoticeSignatory extends Model
{
    protected $table = 'qualification_notice_signatories';

    protected $fillable = ['name', 'position'];
}