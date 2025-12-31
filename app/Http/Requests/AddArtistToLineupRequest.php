<?php

namespace App\Http\Requests;

use App\Enums\ArtistTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddArtistToLineupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artist_id' => 'required|exists:artists,id',
            'tier' => ['required', Rule::enum(ArtistTier::class)],
        ];
    }
}
