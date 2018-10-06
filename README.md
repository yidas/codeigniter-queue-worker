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

- ***Dynamically Workers dispatching** management*

- ***Running in background permanently** without extra libraries* 

---

OUTLINE
-------

- [Demonstration](#demonstration)
- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
    - [How to Design a Worker](#how-to-design-a-worker)
        - [1. Build Initializer](#1-build-initializer)
        - [2. Build Worker](#2-build-worker)
        - [3. Build Listener](#3-build-listener)
    - [Porperties Setting](#porperties-setting)
        - [Public Properties](#public-properties)
- [Usage](#usage)
    - [Running in Background](#running-in-background)
        - [Launcher](#launcher)
        - [Process Status](#process-status)

---

DEMONSTRATION
-------------

Running a listener with 2~5 workers setting added per 3 seconds:

```
$ php index.php job_controller/listen
2018-10-06 14:36:28 - Queue Listener - Job detect
2018-10-06 14:36:28 - Queue Listener - Start dispatch
2018-10-06 14:36:28 - Queue Listener - Dispatch Worker #1 (PID: 13254)
2018-10-06 14:36:28 - Queue Listener - Dispatch Worker #2 (PID: 13256)
2018-10-06 14:36:31 - Queue Listener - Dispatch Worker #3 (PID: 13266)
2018-10-06 14:36:34 - Queue Listener - Job empty
2018-10-06 14:36:34 - Queue Listener - Stop dispatch, total cost: 6.00s
```

---

INTRODUCTION
------------

This library provides a Queue Worker total solution for Codeigniter 3 framework, which includes Listener and Worker for processing new jobs from queue. You may integrate your application queue (such as Redis) with Queue Worker Controller.

Listener could continue to run for detecting new jobs until it is manually stopped or you close your terminal. On the other hand
, Worker could continue to run for processing new jobs until there is no job left.

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

You need to design handlers for your own worker inherited from this library, there are common interfaces as following:

```php
use yidas\queue\worker\Controller as WorkerController;

class My_worker extends WorkerController
{
    // Initializer
    protected function init() {}
    
    // Worker
    protected function handleWork() {}
    
    // Listener
    protected function handleListen() {}
}
```

These handlers are supposed to be designed for detecting the same job queue, but for different purpose. For example, Listener and Worker detect the same Redis list queue, Listener only do dispatching jobs by forking Worker, while Worker continue to takes out jobs and do the processing until job queue is empty.

### How to Design a Worker

#### 1. Build Initializer

```php
protected void init()
```

The `init()` method is the constructor of worker controller, it provides you with an interface for defining initializartion such as Codeigniter library loading.

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function init()
    {
        // Optional autoload 
        $this->load->library('myjobs');

        // Optional shared properties setting
        $this->static = 'static value';
    }
// ...
```

#### 2. Build Worker

```php
protected boolean handleWork(object $static=null)
```

The `handleWork()` method is a processor for Worker that continue to take out jobs and do the processing. When this method returns `false`, that means the job queue is empty and the worker will close itself.   

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function handleWork()
    {
        $job = $this->myjobs->popJob();
        
        // `false` for job not found, which would close the worker itself.
        if (!$job)
            return false;
        
        $this->myjobs->processJob($job);
        
        // `true` for job existing, which would keep handling.
        return true;
    }
// ...
```

#### 3. Build Listener

```php
protected boolean handleListen(object $static=null)
```

The `handleListen()` method is a processor for Listener that dispatches workers to handle jobs while it detects new job by returning `true`. When this method returns `false`, that means the job queue is empty and the listener will stop dispatching.

*Example Code:*
```php
class My_worker extends \yidas\queue\worker\Controller
{
    protected function handleListen()
    {
        // `true` for job existing, which leads to dispatch worker(s).
        // `false` for job not found, which would keep detecting new job
        return $this->myjobs->exists();
    }
// ...
```


### Porperties Setting

You could customize your worker by defining properties.

```php
use yidas\queue\worker\Controller as WorkerController;

class My_worker extends WorkerController
{
    // Setting for that a listener could fork up to 10 workers
    public $workerMaxNum = 10;
    
    // Enable text log writen into specified file for listener and worker
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
|$workerSleep      |integer  |0            |Time interval of worker processes frequency|
|$workerMaxNum     |integer  |5            |Number of max workers|
|$workerStartNum   |integer  |1            |Number of workers at start, less than or equal to $workerMaxNum|
|$workerWaitSeconds|integer  |10           |Waiting time between worker started and next worker starting|
|$workerHeathCheck |boolean  |true         |Enable worker health check for listener|

---

USAGE
-----

There are 3 actions for usage:

- `listen` A listener to manage and dispatch jobs by forking workers.
- `work` A worker to process and solve jobs from queue.
- `launch` A launcher to run `listen` or `work` process in background and keep it running uniquely.

After configurating a queue-worker controller, it is ready to run:

```
$ php index.php myjob/listen
```

Listener would continuously call listener callback funciton, it would dispatch jobs by forking workers while the callback return `true` which means that there has job(s) detected.

Each worker would continuously call worker callback funciton till returning `false`, which means that there are no job detected from the worker. 

Also, the worker `work` could be called by CLI, which the listener is calling the same CLI to fork a worker:

```
$ php index.php myjob/work
```

### Running in Background

#### Launcher

To run Listener or Worker in the background, you could call Launcher to launch process:

```
$ php index.php myjob/launch
```

By default, Launcher would launch `listen` process, you could also lauch `work` by giving parameter:

```
$ php index.php myjob/launch/worker
```

Launcher could keep launching process running uniquely, which prevents multiple same listeners or workers running at the same time. For example, the first time to launch a listener:

```ps
$ php index.php myjob/launch
Success to launch process `listen`: myjob/listen.
Called command: php /srv/ci-project/index.php myjob/listen > /dev/null &
------
USER   PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
user 14650  0.0  0.7 327144 29836 pts/3    R+   15:43   0:00 php /srv/ci-project/index.php myjob/listen
```

Then, when you launch the listener again, Launcher would prevent repeated running:

```ps
$ php index.php myjob/launch
Skip: Same process `listen` is running: myjob/listen.
------
USER   PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
user 14650  0.4  0.9 337764 36616 pts/3    S   15:43   0:00 php /srv/ci-project/index.php myjob/listen
```

For uniquely work scenario, you may use database as application queue, which would lead to race condition if there are multiple workers handling the same jobs. Unlike memcache list, database queue should be processed by only one worker at the same time. 

#### Process Status

After launching a listener, you could check the listener service by command `ps aux|grep php`:

```ps
...
www-data  2278  0.7  1.0 496852 84144 ?        S    Sep25  37:29 php-fpm: pool www
www-data  3129  0.0  0.4 327252 31064 ?        S    Sep10   0:34 php /srv/ci-project/index.php myjob/listen
...
```

According to above, you could manage listener and workers such as killing listener by command `kill 3129`.

Workers would run while listener detected job, the running worker processes would also show in `ps aux|grep php`.

> Manually, you could also use an `&` (an ampersand) at the end of the listener or worker to run in the background.








