<?php

namespace App\Providers;


use App\Language;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppServiceProvider extends ServiceProvider
{

    public function register()
    {
        Factory::guessFactoryNamesUsing(function ($class) {
            return 'Database\\Factories\\' . class_basename($class) . 'Factory';
        });
       
    }

    public function boot()
    {
        Schema::defaultStringLength(191);
        try {
            $all_language = Language::all();
        }catch (\Exception $e){
            $all_language = null;
        }
        Paginator::useBootstrap();
        if (get_static_option('site_force_ssl_redirection') === 'on'){
            URL::forceScheme('https');
        }

        $this->loadViewsFrom(__DIR__.'/../PageBuilder/views','pagebuilder');
    }
}
