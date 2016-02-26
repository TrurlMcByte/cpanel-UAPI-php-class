##cPanel UAPI and API2 PHP class

PHP class to provide an easy to use interface with cPanel’s UAPI and API2 (as of version 1.1).
The implementation is exactly the same for both, the processing done is slightly different behind the scenes.

v1.1 should be backwards compatible.

##Usage

See the example files, but typical useage takes the form of:

###UAPI
```
//load UAPI2 class
$cpuapi = new cpanelAPI('user', 'password', 'cpanel.example.com');

//Set the scope to the module we want to use. in this case, Mysql
//and call the function we want like this. Any arguments are passed into the function as an array, in the form of param => value.
$response = $cpuapi->scope('Mysql')->get_restrictions();
print_r($response);
```

###API2
```
//load API2 class
$cpapi2 = new cpanelAPI('user', 'password', 'cpanel.example.com', 'api2');

//Set the scope to the module we want to use. in this case, SubDomain
//and call the function we want like this. Any arguments are passed into the function as an array, in the form of param => value.
$response = $cpapi2->scope('SubDomain')->addsubdomain(array('rootdomain' => ''domain.com, 'domain' => 'sub'));
print_r($response);
```

###Mixed mode
```
//load UAPI2 class
$cpapi = new \cpanelAPI('user', 'password', 'cpanel.example.com');
// use UAPI by default
$report[] = $cpapi->scope('DomainInfo')->list_domains(); 

// 'regex' not available in UAPI, so switch to API2
$report[] = $cpapi->setApi('api2')->scope('AddonDomain')->listaddondomains(array('regex' => preg_quote('www.example.com')));
print_r($report);
```