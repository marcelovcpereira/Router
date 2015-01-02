<h1>MVC: Router Implementation</h1>
<h4>Implementing a mvc routing class</h4>
<p>Examples</p>
<hr>
```php
use Router\Router;

$tmp = new Router;

//Adds a GET route to a closure
$tmp->get('', function() {
    print "Default Route :P";
});
```

<p>Routing a request to a function:</p>

```php
function myFunction() {
    print "Hello World!";
}

//Adding a route to a defined function
$tmp->get('/sayHello', 'myFunction');
```

<p>Defining controllers to handle routes:</p>

```php
$tmp->get('/html', '\Controller\Controller@html');
$tmp->post('/html', '\Controller\Controller@html');
```

<p>Defining parameters in route:</p>

```php
$tmp->get('/controller/news/{id}', '\Controller\Controller@news');
```

In this case, the router will assign the {id} value as an argument to the 'news' method at 'Controller' class:

```php
namespace Controller;
class Controller
{
    public function news($id)
    {
        print "ID sent: $id";
    }
}
```

<p>Finally, you can define rules to especify the variable type:</p>

```php
$tmp->get('/controller/news/{id}', '\Controller\Controller@findNewsById', array("id"=>"numeric"));
$tmp->get('/controller/news/{name}', '\Controller\Controller@findNewsByName', array("id"=>"letters"));
```
<h4>Current Available Rules:</h4>
<ul>
<li>alphanumeric - [0-9a-zA-Z]</li>
<li>alphanumeric_underscore - [0-9a-zA-Z_]</li>
<li>alphanumeric_full - [0-9a-zA-Z_\-\+]</li>
<li>numeric - [0-9]</li>
<li>letters - [a-zA-Z]</li>
</ul>


