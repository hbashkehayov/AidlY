<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as LumenController;
use Illuminate\Http\Request;

class Controller extends LumenController
{
    /**
     * Custom validation that works with Lumen
     */
    protected function validateRequest(Request $request, array $rules)
    {
        $validator = app('validator')->make($request->all(), $rules);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        return true;
    }
}
