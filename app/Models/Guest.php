<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    protected $fillable = [
        'party_id', 'display', 'nickname', 'aliases', 'pin_hash',
        'recipient_id', 'claimed', 'claimed_at', 'revealed',
    ];

    protected $casts = [
        'aliases' => 'array',
        'claimed' => 'boolean',
        'revealed' => 'boolean',
        'claimed_at' => 'datetime',
    ];

    protected $hidden = ['pin_hash'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'recipient_id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
