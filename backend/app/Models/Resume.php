<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resume extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'phone', 'gender', 'age',
        'districts', 'work_types', 'positions',
        'experience_years', 'salary_min', 'salary_max', 'salary_unit',
        'education', 'availability_date', 'languages', 'skills',
        'raw_text', 'source', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'districts'        => 'array',
            'work_types'       => 'array',
            'positions'        => 'array',
            'languages'        => 'array',
            'skills'           => 'array',
            'experience_years' => 'decimal:1',
            'availability_date' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
