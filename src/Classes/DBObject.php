<?php

namespace Infinacy\DbSync\Classes;

use Illuminate\Contracts\Support\Arrayable;

class DBObject implements Arrayable {

    protected $props;

    public function __construct($props) {
        $this->props = (object)$props;
    }

    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
    }

    public function toArray() {
        return (array)$this->props;
    }
}
