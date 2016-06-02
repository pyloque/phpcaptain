<?php

require('./service.php');
require('./kv.php');
require('./error.php');

class SharedMemory {
    private static $MAGIC_HEADER = "captain";
    private static $MAGIC_HEADER_SIZE;
    private static $MAX_SERVICE_NAME_LEN = 128;
    private static $MAX_KV_NAME_LEN = 128;
    private static $MAX_ITEM_SIZE = 64;
    private static $MAX_ITEMS_PER_SERVICE = 1024;
    private static $MAX_SERVICES = 128;
    private static $MAX_KVS = 64;
    private static $MAX_KV_RECORD_SIZE = 65536;
    private static $SERVICE_NAMES_OFFSET;
    private static $KV_NAMES_OFFSET;
    private static $SERVICE_HEADER_SIZE;
    private static $SERVICE_HEADER_OFFSET;
    private static $MAX_SERVICE_RECORD_SIZE;
    private static $KV_HEADER_SIZE;
    private static $KV_HEADER_OFFSET;

    private $file;

    function __construct($shmfile) {
        self::$MAGIC_HEADER_SIZE = strlen(self::$MAGIC_HEADER);
        self::$SERVICE_NAMES_OFFSET = self::$MAGIC_HEADER_SIZE;
        self::$KV_NAMES_OFFSET = self::$SERVICE_NAMES_OFFSET + self::$MAX_SERVICE_NAME_LEN * self::$MAX_SERVICES;
        self::$SERVICE_HEADER_SIZE = self::$MAX_SERVICES * 4;
        self::$SERVICE_HEADER_OFFSET = self::$KV_NAMES_OFFSET + self::$MAX_KV_NAME_LEN * self::$MAX_KVS;
        self::$KV_HEADER_SIZE = self::$MAX_KVS * 4;
        self::$MAX_SERVICE_RECORD_SIZE = self::$MAX_ITEMS_PER_SERVICE * self::$MAX_ITEM_SIZE;
        self::$KV_HEADER_OFFSET = self::$SERVICE_HEADER_OFFSET + self::$SERVICE_HEADER_SIZE + self::$MAX_SERVICE_RECORD_SIZE * self::$MAX_SERVICES;
        $this->file = fopen($shmfile, "rb");
        $this->verifyMagic();
    }

    function verifyMagic() {
        $bytes = $this->readBytes(0, self::$MAGIC_HEADER_SIZE);
        for($i=0;$i<self::$MAGIC_HEADER_SIZE;$i++) {
            if($bytes[$i] != self::$MAGIC_HEADER[$i]) {
                throw new CaptainException("magic header verification error");
            }
        }
    }

    function readInt($index) {
        fseek($this->file, $index);
        $b = fread($this->file, 4);
        $v = 0;
        for($i=0;$i<strlen($b);$i++) {
            $v |= (ord($b[$i]) << (8 * (3-$i)));
        }
        return $v;
    }

    function readLong($index) {
        fseek($this->file, $index);
        $b = fread($this->file, 8);
        $v = 0;
        for($i=0;$i<strlen($b);$i++) {
            $v |= (ord($b[$i]) << (8 * (7-$i)));
        }
        return $v;
    }

    function readStr($index) {
        $len = $this->readInt($index);
        if($len == 0) {
            return NULL;
        }
        fseek($this->file, $index + 4);
        $bytes = fread($this->file, $len);
        return $bytes;
    }

    function readBytes($index, $length) {
        fseek($this->file, $index);
        $bytes = fread($this->file, $length);
        return $bytes;
    }

    function readService($name, $slot, $last_version) {
        $set = new ServiceSet($name);
        $block = $this->readInt(self::$SERVICE_HEADER_OFFSET + $slot * 4);
        if($block == self::$MAX_SERVICES) {
            return $set;
        }
        $offset = self::$SERVICE_HEADER_OFFSET + self::$SERVICE_HEADER_SIZE + $block * self::$MAX_SERVICE_RECORD_SIZE;
        $version = $this->readLong($offset);
        if($version == $last_version) {
            // version not changed
            return $set;
        }
        $content = $this->readStr($offset + 8);
        if(!is_null($content)) {
            $pairs = explode(',', $content);
            $items = array();
            foreach($pairs as $pair) {
                $parts = explode(':', $pair);
                $item = new ServiceItem($parts[0], $parts[1]);
                array_push($items, $item);
            }
            $set->setItems($items);
        }
        $set->setVersion($version);
        return $set;
    }

    function readKv($key, $slot, $last_version) {
        $kv = new KvItem($key);
        $block = $this->readInt(self::$KV_HEADER_OFFSET + $slot * 4);
        if($block == self::$MAX_KVS) {
            return $kv;
        }
        $offset = self::$KV_HEADER_SIZE + self::$KV_HEADER_OFFSET + $block * self::$MAX_KV_RECORD_SIZE;
        $version = $this->readLong($offset);
        if($version == $last_version) {
            // version not changed
            return $kv;
        }
        $content = $this->readStr($offset + 8);
        $value = json_decode($content, true);
        $kv->setValue($value);
        $kv->setVersion($version);
        return $kv;
    }

    function close() {
        fclose($this->file);
    }

}
?>
