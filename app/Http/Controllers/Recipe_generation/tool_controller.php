<?php

namespace App\Http\Controllers\Recipe_generation;

use App\Enums\ToolType;
use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\UserTool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class tool_controller extends Controller
{
    public function showTools(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Recipe_generation/tool_page', [
            'tools' => UserTool::with('tool')
                ->where('user_id', $user->id)
                ->get()
                ->map(static fn (UserTool $userTool): array => [
                    'id' => $userTool->tool->id,
                    'type' => $userTool->tool->type->value,
                    'type_label' => ucwords(str_replace('_', ' ', $userTool->tool->type->value)),
                ])
                ->all(),
        ]);
    }

    public function showToolCreationPage(Request $request): Response
    {
        return Inertia::render('Recipe_generation/tool_creation_page', [
            'tool_types' => array_map(
                static fn (ToolType $toolType): array => [
                    'value' => $toolType->value,
                    'label' => ucwords(str_replace('_', ' ', $toolType->value)),
                ],
                ToolType::cases(),
            ),
        ]);
    }

    public function createTool(Request $request): RedirectResponse
    {
        $toolData = self::validateNewToolData($request);

        $user = $request->user();

        $user_id = $user->id;
        $tool_id = Tool::insertGetId([
            'type' => $toolData['type'],
        ]);

        UserTool::insert(['user_id' => $user_id, 'tool_id' => $tool_id]);

        return redirect()->route('tools.index')->with('toast', [
            'type' => 'success',
            'message' => 'Tool saved successfully.',
        ]);
    }

    public function deleteTool(Request $request, Tool $tool): RedirectResponse
    {
        $user = $request->user();

        abort_if(! UserTool::where('user_id', $user->id)->where('tool_id', $tool->id)->exists(), 404);

        UserTool::where('user_id', $user->id)->where('tool_id', $tool->id)->delete();

        if (! $tool->users()->exists()) {
            $tool->delete();
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tool removed successfully.',
        ]);
    }

    private static function validateNewToolData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ToolType::cases(), 'value'))],
        ]);
    }
}
