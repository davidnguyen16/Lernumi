<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HealthController
{
    public function show()
    {
        try {
            DB::select('select 1');
            DB::table('migrations')->count();

            return response()->json(['status' => 'ok', 'database' => 'ok']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
