<?php

namespace Infinacy\DbSync\Libraries;

use Illuminate\Database\Capsule\Manager as Capsule;

class DB extends Capsule {

    public function __construct() {
        parent::__construct();
        $this->setAsGlobal();
    }

    public static function instance() {
        return self::$instance;
    }

    public static function init() {
        return new self;
    }
}
