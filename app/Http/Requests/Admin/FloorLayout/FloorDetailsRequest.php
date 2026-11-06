<?php

namespace App\Http\Requests\Admin\FloorLayout;

use App\Models\Floor;
use App\Services\Table\TableLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FloorDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $floor = $this->route('floor');
        $floorId = $floor instanceof Floor ? $floor->id : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('floors', 'name')->ignore($floorId)],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('floors', 'code')->ignore($floorId)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'layout_columns' => ['required', 'integer', 'min:1', 'max:'.TableLayout::MAX_COLUMNS],
            'layout_rows' => ['required', 'integer', 'min:1', 'max:'.TableLayout::MAX_ROWS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A floor with this name already exists.',
            'code.unique' => 'A floor with this code already exists.',
        ];
    }
}
