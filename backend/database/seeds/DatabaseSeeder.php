<?php
namespace Database\Seeders;
use App\Country;
use App\ServiceCity;
use Database\Factories\ServiceCityFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use function fake;

class DatabaseSeeder extends Seeder
{

    public function run()
    {

        $env_val['APP_URL'] = url('/');
        setEnvValue( $env_val);

        // $this->call(UsersTableSeeder::class);
        //$this->call(StaticOptionsTableSeeder::class);
        //update_static_option('site_script_version','1.4.4');
         $permissions = [
//            'wallet-list',
//            'wallet-history',
         ];
       foreach ($permissions as $permission){
          \Spatie\Permission\Models\Permission::updateOrCreate(['name' => $permission],['name' => $permission,'guard_name' => 'admin']);
       }


    }
}
