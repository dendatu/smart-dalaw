<?php

namespace App\Models;

use Database\Factories\AccompanyingVisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccompanyingVisitor extends Model
{
    /** @use HasFactory<AccompanyingVisitorFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'full_name',
        'age',
        'sex',
        'is_pwd',
        'is_pregnant',
        'id_type',
        'id_number',
        'relationship_to_pdl',
    ];

    protected $casts = [
        'age' => 'integer',
        'is_pwd' => 'boolean',
        'is_pregnant' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
