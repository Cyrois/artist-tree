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
     * Auto-generate slug and initial normalized synonym when name is set.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($genre) {
            if (empty($genre->slug)) {
                $genre->slug = Str::slug($genre->name);
            }
            
            // Always ensure the base normalized version is in synonyms
            // so we can match variations against it later.
            $normalized = static::normalizeName($genre->name);
            $synonyms = $genre->synonyms ?? [];
            if (!in_array($normalized, $synonyms)) {
                $synonyms[] = $normalized;
                $genre->synonyms = $synonyms;
            }
        });
    }

    /**
     * Normalize genre name for "smart" comparison.
     * Strips all non-alphabetic characters and lowercases.
     * "R&B" -> "rnb", "Hip-Hop" -> "hiphop"
     */
    public static function normalizeName(string $name): string
    {
        $name = str_replace('&', 'n', $name); // treat & as n (R&B -> Rnb)
        $name = preg_replace('/[^a-zA-Z]/', '', $name); // Strip non-alpha
        return strtolower($name);
    }

    /**
     * Find existing genre by smart lookup or create new one.
     */
    public static function findOrCreateSmart(string $name): self
    {
        $name = trim($name);
        $slug = Str::slug($name);
        $normalized = static::normalizeName($name);

        $likeOperator = (new static)->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // 1. Try exact matches (Slug, Name, or EXACT Raw Synonym)
        $genre = static::where('slug', $slug)
            ->orWhere('name', $likeOperator, $name)
            ->orWhereJsonContains('synonyms', $name) 
            ->first();

        // 2. Try normalized match in synonyms (Fuzzy Match)
        // This catches "RnB" matching "rnb" (from "R&B")
        if (!$genre) {
            $genre = static::whereJsonContains('synonyms', $normalized)->first();
        }

        if ($genre) {
            // Found! "Learn" this new raw variation.
            // If the user passed "RnB", we want to save "RnB" so next time it matches instantly.
            $synonyms = $genre->synonyms ?? [];
            
            if (!in_array($name, $synonyms)) {
                 $synonyms[] = $name;
                 // Ensure uniqueness just in case
                 $genre->update(['synonyms' => array_values(array_unique($synonyms))]);
            }
            return $genre;
        }

        // 3. Create New
        return static::create([
            'name' => $name,
            // Boot will handle slug and normalized synonym
        ]);
    }

    public function artists()
    {
        return $this->belongsToMany(Artist::class, 'artist_genre');
    }
}