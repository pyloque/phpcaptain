<?php

class ServiceItem {
    private $host;
    private $port;
    private $probe;
    const DEFAULT_SERVICE_PROBE = 1024;

    function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
        $this->probe = self::DEFAULT_SERVICE_PROBE;
    }

    function getHost() {
        return $this->host;
    }

    function getPort() {
        return $this->port;
    }

    function getProbe() {
        return $this->probe;
    }

    function setProbe($probe) {
        $this->probe = $probe;
    }

    function getUrlRoot() {
        return sprintf("http://%s:%s", $this->host, $this->port);
    }
}

class ServiceSet {
    private $name;
    private $items;
    private $version;

    function __construct($name) {
        $this->name = $name;
        $this->items = array();
        $this->version = -1;
    }

    function getName() {
        return $this->name;
    }

    function setName($name) {
        $this->name = $name;
    }

    function getItems() {
        return $this->items;
    }

    function setItems($items) {
        $this->items = $items;
    }

    function getVersion() {
        return $this->version;
    }

    function setVersion($version) {
        $this->version = $version;
    }

    function getSize() {
        return count($this->items);
    }

    function isValid() {
        return this.version != -1;
    }

    function randomItem() {
        $len = $this->getSize();
        if($len == 0) {
            return NULL;
        }
        $index = rand(0, $len - 1);
        return $this->items[$index];
    }
}
?>
