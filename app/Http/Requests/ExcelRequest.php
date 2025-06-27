<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExcelRequest extends FormRequest
{
    public function rules()
    {
        return [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(validationError($validator->errors()->toArray()), 422));
    }
}