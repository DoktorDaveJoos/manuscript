<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Support\Trial;
use Illuminate\Http\JsonResponse;

class TrialController extends Controller
{
    public function start(): JsonResponse
    {
        if (License::isActive()) {
            return response()->json([
                'message' => __('A license is already active.'),
            ], 422);
        }

        if (Trial::hasStarted()) {
            return response()->json([
                'message' => __('The free trial has already been used on this device.'),
            ], 422);
        }

        Trial::start();

        return response()->json([
            'message' => __('Your free trial has started.'),
        ]);
    }
}
