<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePreQuestion extends Model
{
    protected $fillable = [
        'service_id',
        'prompt',
        'answer_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function cleanOptions(mixed $options): array
    {
        $clean = [];
        foreach ((array) $options as $option) {
            $label = trim((string) $option);
            if ($label === '' || in_array($label, $clean, true)) {
                continue;
            }
            $clean[] = $label;
        }

        return array_slice($clean, 0, 12);
    }

    /**
     * @return list<string>
     */
    public function choiceOptions(): array
    {
        if ($this->answer_type === 'yes_no') {
            return ['Yes', 'No'];
        }

        if ($this->answer_type === 'choice') {
            return self::cleanOptions($this->options ?? []);
        }

        return [];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'answerType' => $this->answer_type,
            'options' => $this->choiceOptions(),
            'required' => (bool) $this->is_required,
        ];
    }
}
