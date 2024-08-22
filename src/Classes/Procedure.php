<?php

namespace Infinacy\DbSync\Classes;

use Infinacy\DbSync\Libraries\Utility;

class Procedure extends DBObject {

    public function __construct($props) {
        parent::__construct($props);
    }

    public function gist() {
        $gist = ['name' => $this->Name, 'code' => $this->Code];
        return Utility::serializeAndCleanup($gist);
    }

    public function expand($gist) {
        $unserialized = Utility::unserialize($gist);
    }
}
