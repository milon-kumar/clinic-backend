<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentIntakeAnswer extends Model
{
    protected $fillable = [
        'treatment_intake_id',
        'prompt',
        'answer_type',
        'options',
        'is_required',
        'sort_order',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(TreatmentIntake::class, 'treatment_intake_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'answerType' => $this->answer_type,
            'options' => $this->answer_type === 'yes_no'
                ? ['Yes', 'No']
                : array_values($this->options ?? []),
            'required' => (bool) $this->is_required,
            'answer' => $this->answer,
        ];
    }
}
