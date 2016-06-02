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
    private $recentKvs;
    private $recentServices;

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
        $last_version = -1;
        $last_set = NULL;
        if(array_key_exists($name, $this->recentServices)) {
            $last_set = $this->recentServices[$name];
            $last_version = $last_set->getVersion();
        }
        $set = $this->shared->readService($name, $slot, $last_version);
        if($set->isValid()) {
            $this->currentAgent->setProbe(ServiceItem::DEFAULT_SERVICE_PROBE);
            $this->recentServices[$name] = $set;
            return $set->randomItem();
        }
        if($this->currentAgent->getProbe() > 1) {
            $this->currentAgent->setProbe($this->currentAgent->getProbe() >> 1);
        }
        if(!is_null($last_set)) {
            return $last_set->randomItem();
        }
        return NULL;
    }

    function kv($key) {
        $this->shuffleAgent();
        $slot = $this->kvSlots[$key];
        if(is_null($slot)) {
            throw new CaptainException("no such kv:" . $key); 
        }
        $last_version = -1;
        $last_kv = NULL;
        if(array_key_exists($key, $this->recentKvs)) {
            $last_kv = $this->recentKvs[$key];
            $last_version = $last_kv->getVersion();
        }
        $kv = $this->shared->readKv($key, $slot, $last_version);
        if($kv->isValid()) {
            $this->currentAgent->setProbe(ServiceItem::DEFAULT_SERVICE_PROBE);
            $this->recentKvs[$key] = $kv;
            return $kv->getValue();
        }
        if($this->currentAgent->getProbe() > 1) {
            $this->currentAgent->setProbe($this->currentAgent->getProbe() >> 1);
        }
        if(!is_null($last_kv)) {
            return $last_kv->getValue();
        }
        return NULL;
    }

}
?>
