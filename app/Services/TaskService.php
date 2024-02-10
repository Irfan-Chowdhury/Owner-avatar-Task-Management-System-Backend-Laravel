<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Facades\Alert;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{

    public function getAllData(): ?object
    {
        if(auth()->user()->can('task-view-all'))
           return self::getDataForManager();
        else
           return self::getDataForSingleMember();
    }


    private function getDataForManager() : ?object
    {
        return Task::with('project:id,name,code','users:id,name,email')
                ->select('id','project_id', 'name', 'description', 'status')
                ->orderBy('id','DESC')
                ->get();
    }

    private function getDataForSingleMember() : ?object
    {
        return DB::table('task_user')
                ->select(
                    'projects.code as project_code',
                    'tasks.id as id',
                    'tasks.name as name',
                    'tasks.status as status',
                    'users.name as user_name'
                )
                ->join('tasks', 'tasks.id', 'task_user.task_id')
                ->join('projects', 'projects.id', 'tasks.project_id')
                ->join('users', 'users.id', 'task_user.user_id')
                ->where('task_user.user_id', Auth::user()->id)
                ->orderBy('id','DESC')
                ->get();
    }



    public function yajraDataTable()
    {
        if (request()->ajax()) {
            $tasks = self::getAllData();

            return datatables()->of($tasks)
                ->setRowId(function ($row)
                {
                    return $row->id;
                })
                ->addColumn('project_code', function ($row)
                {
                    if(auth()->user()->can('task-view-all'))
                        return $row->project->code;
                    else
                        return $row->project_code;
                })
                ->addColumn('name', function ($row)
                {
                    return $row->name;
                })
                ->addColumn('status', function ($row) {

                    $badgeColor = '';
                    if($row->status === "Pending"){
                        $badgeColor = 'primary';
                    }else if($row->status === "Working"){
                        $badgeColor = 'warning';
                    }else if($row->status === "Done"){
                        $badgeColor = 'success';
                    }
                    return "<span class='p-2 badge badge-". $badgeColor . "'>" . $row->status . "</span>";
                })
                ->addColumn('assigned_to', function ($row)
                {
                    if(auth()->user()->can('task-view-all'))
                        return isset($row->users[0]->name) ? $row->users[0]->name : "<span class='text-danger'>NONE</span>";
                    else
                        return $row->user_name;
                })
                ->addColumn('action', function ($row) {
                    $button = '';
                    $button .= '<a href="tasks/show/'.$row->id.'"  class="btn btn-success btn-sm"><i class="dripicons-preview"></i>View</a>';
                    $button .= '&nbsp;&nbsp;';

                    if(auth()->user()->can('task-edit') && auth()->user()->can('task-delete')) {
                        $button .= '<button type="button" data-id="'.$row->id.'" class="edit btn btn-primary btn-sm"><i class="dripicons-pencil"></i>Edit</button>';
                        $button .= '&nbsp;&nbsp;';
                        $button .= '<button type="button" data-id="'.$row->id.'" class="delete btn btn-danger btn-sm"><i class="dripicons-trash"></i>Delete</button>';
                    }

                    return $button;
                })
                ->rawColumns(['status','assigned_to','action'])
                ->make(true);
        }
    }

    public function createTask(object $request): array
    {
        DB::beginTransaction();
        try {
            $task = Task::create([
                'project_id' => $request->project_id,
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            $task->users()->attach($request->user_id);

            DB::commit();

            return Alert::successMessage('Data Saved Successfully');

        } catch (Exception $exception) {

            DB::rollback();

            return Alert::errorMessage($exception->getMessage());
        }
    }

    public function getTaskById(int $taskId): object
    {
        return Task::with('project:id,name,code', 'users:id,name,email')
                ->select('id', 'project_id', 'name', 'description','status')
                ->find($taskId);
    }

    public function removeTask(int $taskId): array
    {
        try {
            $this->getTaskById($taskId)->delete();

            return Alert::successMessage('Data Deleted Successfully');

        } catch (Exception $exception) {

            return Alert::errorMessage($exception->getMessage());
        }
    }

    public function updateTask(object $request, int $taskId): array
    {
        DB::beginTransaction();
        try {

            $task = self::getTaskById($taskId);
            $task->update([
                    'project_id' => $request->project_id,
                    'name' => $request->name,
                    'description' => $request->description,
                    'status' => $request->status
                ]);

            $task->users()->sync($request->user_id);

            DB::commit();

            return Alert::successMessage('Data Updated Successfully');

        } catch (Exception $exception) {

            DB::rollback();

            return Alert::errorMessage($exception->getMessage());
        }
    }

    public function statusChange(int $taskId, string $status) : array
    {
        try {

            $this->getTaskById($taskId)->update(['status' => $status]);

            return Alert::successMessage('Status Changed Successfully');

        } catch (Exception $exception) {

            return Alert::errorMessage($exception->getMessage());
        }
    }

    public function isAuthorized(int $taskId) : bool
    {
        $data = self::getDataForSingleMember();

        $taskIdsValues = $data->pluck('id')->toArray();

        if(in_array($taskId, $taskIdsValues))
            return true;

        return false;

    }
}