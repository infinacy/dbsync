<?php

namespace Infinacy\DbSync\Classes;

use Infinacy\DbSync\Libraries\Utility;

class Procedures extends DBObjects {

    public function __construct($items) {
        $items = $items->map(fn($item) => $item instanceof Procedure ? $item : new Procedure($item));
        parent::__construct($items);
    }

    public function gist() {
        $gist = ['name' => $this->Name, 'code' => $this->Code];
        return Utility::serializeAndCleanup($gist);
    }

    public function expand($gist) {
        $unserialized = Utility::unserialize($gist);
    }
}
