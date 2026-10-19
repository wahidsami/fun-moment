<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        Response::macro('success',function ($data, $status = 201){
            return response()->json($data, $status);
        });
        Response::macro('error',function ($data, $status = 404){
            return response()->json($data, $status);
        });
    }
}