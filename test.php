<?php
require("./client.php");

$agents = array(new ServiceItem("localhost", 6790));
$client = new CaptainClient($agents, "/tmp/ramdisk/captain/agent");
$client->watch("service1", "service2")->watchKv("project_settings_service1", "sample");
while(true) {
    var_dump($client->select("service1")->getUrlRoot());
    var_dump($client->select("service2")->getUrlRoot());
    var_dump($client->kv("project_settings_service1"));
    var_dump($client->kv("sample"));
    sleep(1);
}
?>
