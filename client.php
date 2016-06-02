<?php

require('./shared.php');

class CaptainClient {
    
    private $agents;
    private $services;
    private $kvs;
    private $serviceSlots;
    private $kvSlots;
    private $shared;
    private $currentAgent;

    function __construct($agents, $shmfile) {
        $this->agents = $agents;
        $this->services = array();
        $this->kvs = array();
        $this->serviceSlots = array();
        $this->kvSlots = array();
        $this->recentKvs = array();
        $this->recentServices = array();
        $this->shared = new SharedMemory($shmfile);
    }

    function shuffleAgent() {
        $sum_probe = 0;
        foreach($this->agents as $agent) {
            $sum_probe += $agent->getProbe();
        }
        $rand_probe = rand(0, $sum_probe - 1);
        $acc_probe = 0;
        foreach($this->agents as $agent) {
            $acc_probe += $agent->getProbe();
            if($acc_probe > $rand_probe) {
                $this->currentAgent = $agent;
                break;
            }
        }
    }

    function urlRoot() {
        if(is_null($this->currentAgent)) {
            $this->shuffleAgent();
        }
        return $this->currentAgent->getUrlRoot();
    }

    function watch($name) {
        $names = func_get_args();
        $pairs = array();
        foreach($names as $name) {
            array_push($pairs, "name=" . $name);
        }
        $query = join($pairs, '&');
        $url = sprintf("%s/api/service/watch?%s", $this->urlRoot(), $query);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        curl_close($curl);
        if($result == false) {
            throw new CaptainException("watch service failed");
        }
        $json = json_decode($result, true);
        $slots = $json["slots"];
        foreach($slots as $key => $value) {
            $this->serviceSlots[$key] = $value;
        }
        return $this;
    }

    function watchKv($key) {
        $keys = func_get_args();
        $pairs = array();
        foreach($keys as $key) {
            array_push($pairs, "key=" . $key);
        }
        $query = join($pairs, '&');
        $url = sprintf("%s/api/kv/watch?%s", $this->urlRoot(), $query);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        curl_close($curl);
        if($result == false) {
            throw new CaptainException("watch kv failed");
        }
        $json = json_decode($result, true);
        $slots = $json["slots"];
        foreach($slots as $key => $value) {
            $this->kvSlots[$key] = $value;
        }
        return $this;
    }

    function select($name) {
        $this->shuffleAgent();
        $slot = $this->serviceSlots[$name];
        if(is_null($slot)) {
            throw new CaptainException("no such service:" . $name); 
        }
        $set = $this->shared->readService($name, $slot);
        if($set->isValid()) {
            $this->currentAgent->setProbe(ServiceItem::DEFAULT_SERVICE_PROBE);
        } else {
            if($this->currentAgent->getProbe() > 1) {
                $this->currentAgent->setProbe($this->currentAgent->getProbe() >> 1);
            }
        }
        return $set->randomItem();
    }

    function kv($key) {
        $this->shuffleAgent();
        $slot = $this->kvSlots[$key];
        if(is_null($slot)) {
            throw new CaptainException("no such kv:" . $key); 
        }
        $kv = $this->shared->readKv($key, $slot);
        if($kv->isValid()) {
            $this->currentAgent->setProbe(ServiceItem::DEFAULT_SERVICE_PROBE);
        } else {
            if($this->currentAgent->getProbe() > 1) {
                $this->currentAgent->setProbe($this->currentAgent->getProbe() >> 1);
            }
        }
        return $kv->getValue();
    }

}
?>
