Captain
--------------------------
Captain is yet another service discovery implementation based on redis.
Captain sacrifices a little high availability for simplicity and performance.
In most cases, we dont have so many machines as google/amazon.
The possibility of machine crashing is very low, high Availability is not so abviously important yet.
But the market only provides zookeeper/etcd/consul, they are complex, at least much complexer compared with captain.
https://github.com/pyloque/captain

Speciality
------------------------
Php has no stabilized multithread support. So agent is provided to communite with php client using ramdisk file.


Use Captain PHP Client
-----------------------
```php
git clone https://github.com/pyloque/phpcaptain.git

require("./client.php");

$agents = array(new ServiceItem("localhost", 6790));
$client = new CaptainClient($agents, "/tmp/ramdisk/captain/agent");
$client->watch("service1", "service2")->watchKv("project_settings_service1", "sample");
while(true) {
    sleep(1);
    var_dump($client->select("service1"));
    var_dump($client->select("service2"));
    var_dump($client->kv("project_settings_service1"));
    var_dump($client->kv("sample"));
}
```
