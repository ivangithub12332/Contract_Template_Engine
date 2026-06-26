<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVariableRequest;
use App\Http\Requests\UpdateVariableRequest;
use App\Models\Template;
use App\Models\Variable;

class VariableController extends Controller
{
    /**
     * Manually register a variable on a template (in addition to auto-detected ones).
     */
    public function store(StoreVariableRequest $request, Template $template)
    {
        $variable = $template->variables()->create($request->validated());

        return response()->json($this->formatVariable($variable), 201);
    }

    /**
     * Update a variable's form metadata. The key is immutable since it is tied
     * to the {{key}} placeholder already baked into the template file.
     */
    public function update(UpdateVariableRequest $request, Variable $variable)
    {
        $variable->update($request->validated());

        return response()->json($this->formatVariable($variable->fresh()));
    }

    public function destroy(Variable $variable)
    {
        $variable->delete();

        return response()->json(null, 204);
    }

    private function formatVariable(Variable $variable): array
    {
        return [
            'id' => $variable->id,
            'key' => $variable->key,
            'label' => $variable->label,
            'type' => $variable->type,
            'required' => $variable->required,
            'default_value' => $variable->default_value,
            'hint' => $variable->hint,
            'options' => $variable->options,
        ];
    }
}
