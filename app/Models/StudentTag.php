<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTag extends Model
{
    use HasFactory;
    protected $fillable = ['student_id', 'created_by', 'tag', 'note'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
