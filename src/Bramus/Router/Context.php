<?php
namespace Bramus\Router;

class Context
{

    private $data;
    private $params;

    private $stop = false;

    public function __construct($params = [], &$data = []) {
        if(is_array($data)) {
            $this->data = &$data;
        }
        if(is_array($params)) {
            $this->params = $params;
        }
    }

    public function getParams() {
        return $this->params;
    }

    public function &getData() {
        return $this->data;
    }

    public function setStop($bool){
        $this->stop = !!$bool;
    }

    public function getStop() {
        return $this->stop;
    }
}