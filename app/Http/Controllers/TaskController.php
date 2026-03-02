<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::orderBy('created_at', 'desc')->get();
        return $this->jsonResponse(true, $tasks, 'Data berhasil diambil', 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $task = Task::create([
            'title'  => $request->title,
            'status' => 'pending',
        ]);

        return $this->jsonResponse(true, $task, 'Task berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::find($id);
        if (!$task) {
            return $this->jsonResponse(false, null, 'Task tidak ditemukan', 404);
        }

        $request->validate([
            'title'  => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,done',
        ]);

        $task->update($request->only(['title', 'status']));

        return $this->jsonResponse(true, $task->fresh(), 'Task berhasil diupdate', 200);
    }

    public function destroy($id)
    {
        $task = Task::find($id);
        if (!$task) {
            return $this->jsonResponse(false, null, 'Task tidak ditemukan', 404);
        }

        $task->delete();
        return $this->jsonResponse(true, null, 'Task berhasil dihapus', 200);
    }
}
