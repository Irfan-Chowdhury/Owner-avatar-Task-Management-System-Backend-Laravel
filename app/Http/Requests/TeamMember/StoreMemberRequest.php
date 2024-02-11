<?php

namespace App\Http\Requests\TeamMember;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'employee_id' => 'required|string|unique:users,employee_id,NULL,id,deleted_at,NULL',
            'position' => 'required|string|max:255',
            'password' => 'required|string|min:5|confirmed',
        ];
    }
}
