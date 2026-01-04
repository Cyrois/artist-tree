<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'synonyms',
    ];

    protected $casts = [
        'synonyms' => 'array',
    ];

    /**
     * Auto-generate slug when name is set.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($genre) => $genre->slug = $genre->slug ?? Str::slug($genre->name));
    }

    public function artists()
    {
        return $this->belongsToMany(Artist::class, 'artist_genre');
    }
}