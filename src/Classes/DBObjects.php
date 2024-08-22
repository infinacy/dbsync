<?php

namespace Infinacy\DbSync\Classes;

use Illuminate\Support\Collection;

class DBObjects extends Collection {
    public function __construct($items = []) {
        parent::__construct($items);
    }
}
