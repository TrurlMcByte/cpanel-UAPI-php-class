##cPanel UAPI and API2 PHP class

PHP class to provide an easy to use interface with cPanel’s UAPI and API2 (as of version 1.1).
The implementation is exactly the same for both, the processing done is slightly different behind the scenes.

v1.1 should be backwards compatible.

##Usage

See the example files, but typical useage takes the form of:

###Mixed mode
```PHP
//use UAPI2 by default
$cpapi = new \cpanelAPI('user', 'password', 'cpanel.example.com');
//Set the scope to the module we want to use. in this case, DomainInfo
// and call the function we want like this.
$report[] = $cpapi->DomainInfo->list_domains();

// 'regex' not available in UAPI, so switch to API2
// Any arguments are passed into the function as an array, in the form of param => value.
$report[] = $cpapi->API2->AddonDomain->listaddondomains(array('regex' => preg_quote('www.example1.com')));

// now API already switched, so next query
$report[] = $cpapi->AddonDomain->listaddondomains(array('regex' => preg_quote('www.example2.com')));

// really scope also already set...
$report[] = $cpapi->listaddondomains(array('regex' => preg_quote('www.example3.com')));

// ??? magic call 'same' with short array syntax
$report[] = $cpapi->same(['regex' => preg_quote('www.example4.com')]);
// STOP IT!
$report[] = $cpapi(['regex' => preg_quote('www.example4.com')]);

print_r($report);
```

Also you may use bit crazy way to set current api/scope/method like
`$cpapi->API2->AddonDomain->listaddondomains;`


###ToDo

Improve output structure
