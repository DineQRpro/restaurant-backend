<?php

namespace App\Http\Requests\Api\V1\Floors;

use App\Services\Table\TableLayout;
use Illuminate\Foundation\Http\FormRequest;

class SaveFloorLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('ensure_placements') && $this->missing('placements')) {
            $this->merge(['placements' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'layout_version' => ['required', 'integer', 'min:1'],
            'placements' => ['present', 'array'],
            'placements.*.table_id' => ['required', 'integer'],
            'placements.*.x' => ['required', 'integer', 'min:0', 'max:'.(TableLayout::MAX_COLUMNS - 1)],
            'placements.*.y' => ['required', 'integer', 'min:0', 'max:'.(TableLayout::MAX_ROWS - 1)],
        ];
    }
}
