<p align="center">
    <a href="https://codeigniter.com/" target="_blank">
        <img src="https://codeigniter.com/assets/images/ci-logo-big.png" height="100px">
    </a>
    <h1 align="center">CodeIgniter Queue Worker</h1>
    <br>
</p>

CodeIgniter 3 Queue Worker Management Controller

[![Latest Stable Version](https://poser.pugx.org/yidas/codeigniter-queue-worker/v/stable?format=flat-square)](https://packagist.org/packages/yidas/codeigniter-queue-worker)
[![Latest Unstable Version](https://poser.pugx.org/yidas/codeigniter-queue-worker/v/unstable?format=flat-square)](https://packagist.org/packages/yidas/codeigniter-queue-worker)
[![License](https://poser.pugx.org/yidas/codeigniter-queue-worker/license?format=flat-square)](https://packagist.org/packages/yidas/codeigniter-queue-worker)

This Queue Worker extension is collected into [yidas/codeigniter-pack](https://github.com/yidas/codeigniter-pack) which is a complete solution for Codeigniter framework.

Features
--------

- ***Multi-Processing** implementation on native PHP-CLI*

- *Easy way to manage **multiple workers/processes***

- *Standard Base Controller for inheritance* 

---

OUTLINE
-------

- [Demonstration](#demonstration)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
    - [How to Design a Worker](#how-to-design-a-worker)
        - [1. Build Initializer](#1-build-initializer)
        - [2. Build Listener](#2-build-listener)
        - [3. Build Worker](#3-build-worker)
    - [Porperties Setting](#porperties-setting)
        - [Public Properties](#public-properties)
- [Usage](#usage)

---

DEMONSTRATION
-------------


```
$ php ./index.php worker_controller/listener
```

Check log:

```
Worker Manager start assignment at: 2018-09-08 17:49:15
Worker #1 create at: 2018-09-08 17:49:15
Worker #2 create at: 2018-09-08 17:49:25
Worker #1 close at: 2018-09-08 17:49:28 | cost: 13.51s
Worker #2 close at: 2018-09-08 17:49:29 | cost: 4.93s
Worker Manager stop assignment at: 2018-09-08 17:49:30, total cost: 15.02s
```
---

REQUIREMENTS
------------
This library requires the following:

- PHP CLI 5.4.0+
- CodeIgniter 3.0.0+

---

INSTALLATION
------------

Run Composer in your Codeigniter project under the folder `\application`:

    composer require yidas/codeigniter-queue-worker
    
Check Codeigniter `application/config/config.php`:

```php
$config['composer_autoload'] = TRUE;
```
    
> You could customize the vendor path into `$config['composer_autoload']`

---

CONFIGURATION
-------------

You need to design porcesses or set properties for your own worker inherited from this library, there are common interfaces as following:

```php
use yidas\queue\worker\Controller as WorkerController;

class My_worker extends WorkerController
{
    // Initializer
    protected function init() {}
    
    // Listener
    protected function listenerCallback() {}
    
    // Worker
    protected function workerCallback() {}
}
```

### How to Design a Worker

#### 1. Build Initializer

```php
protected void init()
```

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function init()
    {
        // Optional autoload 
        $this->load->library('myqueue');

        // Optional shared properties setting
        $this->static = 'static value';
    }
// ...
```

#### 2. Build Listener

```php
protected boolean listenerCallback(object $static=null)
```

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function listenerCallback()
    {
        // `true` for task existing
        return $this->myqueue->exists();
    }
// ...
```

#### 3. Build Worker

```php
protected boolean workerCallback(object $static=null)
```

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function workerCallback()
    {
        // `false` for task not found
        return $this->myqueue->processTask();
    }
// ...
```

### Porperties Setting

You could customize your worker by defining properties.

```php
use yidas\queue\worker\Controller as WorkerController;

class My_worker extends WorkerController
{
    // Set for that a listener only create a worker
    // set to 1 could prevent race condition depended on your queue structure
    public $workerMaxNum = 1;
    
    // Enable text log writen into specified file
    public $logPath = 'tmp/my-worker.log';
}
```

#### Public Properties

|Property          |Type     |Deafult      |Description|
|:--               |:--      |:--          |:--        |
|$debug            |boolean  |true         |Debug mode |
|$logPath          |string   |null         |Log file path|
|$phpCommand       |string   |'php'        |PHP CLI command for current environment|
|$listenerSleep    |integer  |3            |Time interval of listen frequency on idle|
|$workerSleep      |integer  |0            |Time interval of worker processes|
|$workerMaxNum     |integer  |5            |Number of max workers|
|$workerStartNum   |integer  |1            |Number of workers at start, less than or equal to $workerMaxNum|
|$workerWaitSeconds|integer  |10           |Waiting time between worker started and next worker starting|


---

USAGE
-----

After configurating a worker, this worker controller is ready to go:

```
$ php ./index.php my_worker/listener
```

Listener would continuously process listener callback funciton, it would assign works by forking workers while the callback return `true` which means that there has task(s) detected.

Each worker would continuously process worker callback funciton till returning `false`, which means that there are no task detected from the worker. 

The worker could be called by CLI, for example `$ php ./index.php my_worker/worker`, which the listener is calling the same CLI to fork a worker.



