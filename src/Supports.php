<?php

namespace yzh52521\Flysystem\Oss;

use League\Flysystem\Config;

class Supports
{

    private $flashData = null;


    public function setFlashData($data = null)
    {
        $this->flashData = $data;
    }

    public function getFlashData()
    {
        $flash           = $this->flashData;
        $this->flashData = null;
        return $flash;
    }

}
