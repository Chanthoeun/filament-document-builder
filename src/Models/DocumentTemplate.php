<?php

namespace Chanthoeun\FilamentDocumentBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'content',
        'page_settings',
    ];

    protected $casts = [
        'content' => 'array',
        'page_settings' => 'array',
    ];
}
