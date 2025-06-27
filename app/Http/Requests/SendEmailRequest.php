<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendEmailRequest extends FormRequest
{
    public function rules()
    {
        return [
            'mail' => 'required|array',
            'mail.*.to' => 'required|email',
            'mail.*.content' => 'nullable|string',
            'mail.*.subject' => 'nullable|string',
            'mail.*.priority' => 'required|string|in:low,medium,high',
            'mail.*.attachment' => 'nullable|array',
            'mail.*.attachment.*' => 'nullable|url'
        ];
    }
    public function messages()
    {
        return [
            'mail.required' => 'Data email ("mail") wajib diisi.',
            'mail.array' => 'Data email ("mail") harus berupa array.',
            'mail.*.to.required' => 'Email penerima ("to") wajib diisi.',
            'mail.*.to.email' => 'Format email pada kolom "to" tidak valid.',
            'mail.*.content.string' => 'Isi email ("content") harus berupa teks.',
            'mail.*.subject.string' => 'Subjek email ("subject") harus berupa teks.',
            'mail.*.priority.required' => 'Prioritas email wajib diisi.',
            'mail.*.priority.string' => 'Prioritas email harus berupa teks.',
            'mail.*.priority.in' => 'Prioritas email harus salah satu dari: low, medium, high.',
            'mail.*.attachment.array' => 'Attachment harus berupa array.',
            'mail.*.attachment.*.url' => 'Setiap attachment harus berupa URL yang valid.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}