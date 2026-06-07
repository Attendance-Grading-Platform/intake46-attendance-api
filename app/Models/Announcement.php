<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;
    protected $fillable = ['cohort_id', 'author_id', 'title', 'body', 'published_at'];
    protected $casts = [
        'published_at' => 'datetime',
    ];
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
