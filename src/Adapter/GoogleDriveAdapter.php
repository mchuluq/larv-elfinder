<?php

namespace Mchuluq\Larv\Elfinder\Adapter;

class GoogleDriveAdapter extends \Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter{
    
    public function getService(){
        return $this->service;
    }
}
