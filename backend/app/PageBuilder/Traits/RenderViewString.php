<?php


namespace App\PageBuilder\Traits;


trait RenderViewString
{

    public function viewPath(){
        return 'pagebuilder::';
    }
    public function renderBlade($blade_name, $data = []) : string
    {
        $data = array_merge($data, ['settings' => $this->get_settings()]);
        return view($this->viewPath(). $blade_name, $data)->render();
    }
}
