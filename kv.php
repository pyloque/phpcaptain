<?php

class KvItem {
    private $key;
    private $version;
    private $value;

    function __construct($key) {
        $this->key = $key;
        $this->version = -1;
    }

    function getKey() {
        return $this->key;
    }

    function getVersion() {
        return $this->version;
    }

    function setVersion($version) {
        $this->version = $version;
    }

    function getValue() {
        return $this->value;
    }

    function setValue($value) {
        $this->value = $value;
    }

    function isValid() {
        return $this->version == -1;
    }
}
?>
