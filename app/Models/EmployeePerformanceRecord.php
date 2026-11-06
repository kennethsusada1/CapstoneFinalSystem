<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'review_year', 'ipcr_rating', 'source'])]
class EmployeePerformanceRecord extends Model
{
    protected function casts(): array
    {
        return [
            'review_year' => 'integer',
            'ipcr_rating' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EmployeeRecord, $this>
     */
    public function employeeRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeRecord::class, 'employee_id', 'employee_id');
    }
}
