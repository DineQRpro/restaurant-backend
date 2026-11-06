<?php

namespace App\Http\Requests\Admin\FloorLayout;

use App\Services\Table\TableLayout;
use Illuminate\Foundation\Http\FormRequest;

class AssignFloorTableRequest extends FormRequest
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
        return [
            'layout_version' => ['required', 'integer', 'min:1'],
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'x' => ['required', 'integer', 'min:0', 'max:'.(TableLayout::MAX_COLUMNS - 1)],
            'y' => ['required', 'integer', 'min:0', 'max:'.(TableLayout::MAX_ROWS - 1)],
        ];
    }
}
