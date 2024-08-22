<?php

namespace Infinacy\DbSync\Libraries;

class Config {

    private $params;

    public function __construct() {
        $this->params = [];
    }

    public function getConfig($name) {
        if (!isset($this->params[$name])) {
            $config_file = __DIR__ . '/../config/' . $name . '.php';
            if (file_exists($config_file)) {
                $params = require($config_file);
                $this->params[$name] = $params;
            }
        }
        return isset($this->params[$name]) ? new \ArrayObject($this->params[$name], \ArrayObject::ARRAY_AS_PROPS) : new \stdClass;
    }
}
