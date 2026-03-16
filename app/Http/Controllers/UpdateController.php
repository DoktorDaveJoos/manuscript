<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Native\Desktop\Facades\AutoUpdater;

class UpdateController extends Controller
{
    public function check(): JsonResponse
    {
        AutoUpdater::checkForUpdates();

        return response()->json(['status' => 'checking']);
    }

    public function download(): JsonResponse
    {
        AutoUpdater::downloadUpdate();

        return response()->json(['status' => 'downloading']);
    }

    public function install(): JsonResponse
    {
        AutoUpdater::quitAndInstall();

        return response()->json(['status' => 'installing']);
    }
}
