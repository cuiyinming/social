<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class Request extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, $this->response(
            $this->formatErrors($validator)
        ));
    }

    /**
     * Get the proper failed validation response for the request.
     *
     * @param  array $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        return $this->json_response(502, array_first($errors), $errors);
    }

    private function formatErrors($validator)
    {
        return $validator->errors()->all();
    }

    private function json_response($code = 0, $message = 'OK', $data = null)
    {
        if (is_array($code) || is_object($code)) {
            $data = $code;
            $code = 200;
        }

        if (is_null($data)) {
            $data = new stdClass();
        }

        $ret = [
            'code' => $code,
            'msg' => $message,
            'data' => $data,
        ];


        $response = response()->json($ret);

        $callback = request()->get('_callback');
        if ($callback) {
            $response->withCallback($callback);
        }
        return $response;
    }

}
