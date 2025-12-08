<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $ignoreId = $this->input('id') ?? $this->user()->id;
        return [
            'name' => ['string', 'max:255'],
            'email' => ['email', 'max:255', Rule::unique(User::class)->ignore($ignoreId)],
            'password' => ['nullable', 'confirmed', Password::defaults()], // Permite que sea opcional/nulo
            'password_confirmation' => ['nullable'],
            'user_status' => ['integer', 'in:0,1,2']
        ];
    }
}
