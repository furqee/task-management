<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $allTasks = Task::all();
        $statuses = Status::all();

        $tasks = [];
        foreach ($allTasks as $task) {
            $tasks[$task->status_id][] = [
                'title' => $task->title,
                'description' => $task->description,
                'id' => $task->id,
                'status_id' => $task->status_id,
                'props' => [
                    'style' => [
                        'backgroundColor' => $this->colorPicker()
                    ],

                ],
            ];
        }

        $columns = [];
        foreach ($statuses as $status) {
            $columns[] = [
                'id' => $status->id,
                'name' => $status->status,
                'tasks' => $tasks[$status->id] ?? [],
            ];
        }

        $tasks = [
            'columns' => $columns,
        ];

        return response()->json([
            'tasks' => $tasks,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update(Request $request): JsonResponse
    {
        $this->validate($request, [
            'id' => 'required|exists:tasks',
            'title' => 'required|max:100',
            'status_id' => 'required|exists:statuses,id',
            'description' => 'required|max:500',
        ]);

        $task = Task::find($request->id);
        $task->update($request->only('title', 'status_id', 'description'));

        return response()->json(['message' => 'Task updated successfully.']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $this->validate($request, [
            'id' => 'required|exists:tasks',
            'status_id' => 'required|exists:statuses,id',
        ]);

        $task = Task::find($request->id);
        $task->update($request->only('status_id'));

        return response()->json([
            'message' => "$task->title status changed to ".$task->status->status
        ]);
    }

    private function colorPicker(): string
    {
        $cardColors = [
            'azure',
            'beige',
            'bisque',
            'blanchedalmond',
            'burlywood',
            'cornsilk',
            'gainsboro',
            'ghostwhite',
            'ivory',
            'khaki'
        ];
        return $cardColors[rand(0, 9)];
    }
}
