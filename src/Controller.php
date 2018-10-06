<?php

namespace yidas\queue\worker;

use Exception;
use CI_Controller;

/**
 * Worker Manage Controller
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 * @version 1.0.0
 */
class Controller extends CI_Controller
{
    /**
     * Debug mode
     *
     * @var boolean
     */
    public $debug = true;

    /**
     * Log file path
     *
     * @var string
     */
    public $logPath;

    /**
     * PHP CLI command for current environment
     *
     * @var string
     */
    public $phpCommand = 'php';

    /**
     * Time interval of listen frequency on idle
     *
     * @var integer Seconds
     */
    public $listenerSleep = 3;

    /**
     * Time interval of worker processes frequency
     * 
     * The time between a job handle done and the next job catch
     *
     * @var integer Seconds
     */
    public $workerSleep = 0;

    /**
     * Number of max workers
     *
     * @var integer
     */
    public $workerMaxNum = 5;

    /**
     * Number of workers at start, less than or equal to $workerMaxNum
     *
     * @var integer
     */
    public $workerStartNum = 1;

    /**
     * Waiting time between worker started and next worker starting
     *
     * @var integer Seconds
     */
    public $workerWaitSeconds = 10;

    /**
     * Enable worker health check for listener
     *
     * @var boolean
     */
    public $workerHeathCheck = true;

    /**
     * Time interval of single processes frequency
     *
     * @var integer Seconds
     */
    public $singleSleep = 3;

    /**
     * Single process unique lock time for unexpected shutdown
     *
     * @var integer Seconds
     */
    public $singleLockTimeout = 15;

    /**
     * Descriptorspec for proc_open()
     *
     * @var array
     * @see http://php.net/manual/en/function.proc-open.php
     */
    protected static $_procDescriptorspec = [
        ["pipe", "r"],
        ["pipe", "w"],
        ["pipe", "w"],
        ];

    /**
     * Static listener object for injecting into customized callback process
     *
     * @var object
     */
    protected $_staticListen;

    /**
     * Static worker object for injecting into customized callback process
     *
     * @var object
     */
    protected $_staticWork;

    /**
     * Static single object for injecting into customized callback process
     *
     * @var object
     */
    protected $_staticSingle;

    /**
     * Worker process running stack
     *
     * @var array Worker ID => OS PID
     */
    protected $_pidStack = [];
    
    function __construct() 
    {
        // CLI only
        if (php_sapi_name() != "cli") {
            die('Access denied');
        }

        parent::__construct();

        // Init constructor hook
        if (method_exists($this, 'init')) {
            // You may need to set config to prevent any continuous growth usage 
            // such as `$this->db->save_queries = false;`
            return $this->init();
        }
    }

    /**
     * Action for activating a worker listener
     *
     * @return void
     */
    public function listen()
    {
        // Env check
        if (!$this->_isLinux()) {
            die("Error environment: Queue Listener requires Linux OS, you could use `work` or `single` instead.");
        }
        
        // Pre-work check
        if (!method_exists($this, 'handleListen'))
            throw new Exception("You need to declare `handleListen()` method in your worker controller.", 500);
        if (!method_exists($this, 'handleWork'))
            throw new Exception("You need to declare `handleWork()` method in your worker controller.", 500);
        if ($this->logPath && !file_exists($this->logPath)) {
            // Try to access or create log file
            if ($this->_log('')) {
                throw new Exception("Log file doesn't exist: `{$this->logPath}`.", 500);
            }
        }

        // INI setting
        if ($this->debug) {
            error_reporting(-1);
            ini_set('display_errors', 1);
        }
        set_time_limit(0);

        // Worker command builder
        // Be careful to avoid infinite loop by opening listener itself
        $workerAction = 'work';
        $route = $this->router->fetch_directory() . $this->router->fetch_class() . "/{$workerAction}";
        $workerCmd = "{$this->phpCommand} " . FCPATH . "index.php {$route}";

        // Static variables
        $startTime = 0;
        $workerCount = 0;
        $workingFlag = false;

        // Setting check
        $this->workerMaxNum = ($this->workerMaxNum >= 1) ? floor($this->workerMaxNum) : 1;
        $this->workerStartNum = ($this->workerStartNum <= $this->workerMaxNum) ? floor($this->workerStartNum) : $this->workerMaxNum;
        $this->workerWaitSeconds = ($this->workerWaitSeconds >= 1) ? $this->workerWaitSeconds : 10;

        while (true) {

            // Loading insurance
            sleep(0.1);
            
            // Call customized listener process, assigns works while catching true by callback return
        	$hasEvent = ($this->handleListen($this->_staticListen)) ? true : false;

            // Start works if exists
            if ($hasEvent) {  

                // First time to assign works
                if (!$workingFlag) {
                    $workingFlag = true;
                    $startTime = microtime(true);
                    $this->_log("Queue Listener - Job detect");
                    $this->_log("Queue Listener - Start dispatch");

                    if ($this->workerStartNum > 1) {
                        // Execute extra worker numbers
                        for ($i=1; $i < $this->workerStartNum ; $i++) { 
                            $workerCount ++;
                            $r = $this->_workerCmd($workerCmd, $workerCount);
                        }
                    }
                }

                // Max running worker numbers check, otherwise keeps dispatching more workers
                if ($this->workerMaxNum <= $workerCount) {

                    // Worker heath check
                    if ($this->workerHeathCheck) {
                        foreach ($this->_pidStack as $id => $pid) {
                            $isAlive = $this->_isPidAlive($pid);
                            if (!$isAlive) {
                                $this->_log("Queue Listener - Worker health check: Missing #{$id} (PID: {$pid})");
                                $r = $this->_workerCmd($workerCmd, $id);
                            }
                        }
                    }

                    sleep($this->workerWaitSeconds);
                    continue;
                }

                // Assign works
                $workerCount ++;
                // Create a worker
                $r = $this->_workerCmd($workerCmd, $workerCount);

                sleep($this->workerWaitSeconds);
                continue;
            }

            // The end of assignment (No more work), close the assignment
            if ($workingFlag) {
                $workingFlag = false;
                $workerCount = 0;
                // Clear worker stack
                $this->_pidStack = [];
                $costSeconds = number_format(microtime(true) - $startTime, 2, '.', '');
                $this->_log("Queue Listener - Job empty");
                $this->_log("Queue Listener - Stop dispatch, total cost: {$costSeconds}s");
            }
            
            // Idle
            if ($this->listenerSleep) {
                sleep($this->listenerSleep);
            }
        }
    }
    
    /**
     * Action for creating a worker 
     *
     * @param integer $id
     * @return void
     */
    public function work($id=1)
    {
        // Pre-work check
        if (!method_exists($this, 'handleWork'))
            throw new Exception("You need to declare `handleWork()` method in your worker controller.", 500);
        
        // INI setting
        if ($this->debug) {
            error_reporting(-1);
            ini_set('display_errors', 1);
        }
        set_time_limit(0);

        // Start worker
        $startTime = microtime(true);
        $pid = getmypid();
        // Print worker close
        $this->_print("Queue Worker - Create #{$id} (PID: {$pid})");

        // Call customized worker process, stops till catch false by callback return
        while ($this->handleWork($this->_staticWork)) {
            // Sleep if set
            if ($this->workerSleep) {
                sleep($this->workerSleep);
            }
            // Loading insurance
            sleep(0.1);
        }

        // Print worker close
        $costSeconds = number_format(microtime(true) - $startTime, 2, '.', '');
        $this->_print("Queue Worker - Close #{$id} (PID: {$pid}) | cost: {$costSeconds}s");

        return;
    }

    /**
     * Launcher for guaranteeing unique process
     * 
     * This launcher would launch specified process if there are no any other same process running
     * by launcher. Using this for launching `listen` could ensure there are always one listener 
     * running at the same time with repeated launch calling likes crontab, which could also ensure
     * listener process would never gone away.
     *
     * @param string $action
     * @return void
     */
    public function launch($action='listen')
    {
        // Env check
        if (!$this->_isLinux()) {
            die("Error environment: Queue Launcher requires Linux OS, you could use `work` or `single` instead.");
        }
        
        // Action check
        if (!in_array($action, ['listen', 'work'])) {
            die("Action: `{$action}` is invalid for Launcher.");
        }
        
        // Null so far
        $logPath = '/dev/null';
        
        // Action command builder
        $route = $this->router->fetch_directory() . $this->router->fetch_class() . "/{$action}";
        $cmd = "{$this->phpCommand} " . FCPATH . "index.php {$route}";

        // Check process exists
        $search = str_replace('/', '\/', $route);
        // $result = shell_exec("pgrep -f \"{$search}\""); // Lacks of display info
        // Find out the process by name
        $psCmd = "ps aux | grep \"{$search}\" | grep -v grep";
        $psInfoCmd = "ps aux | egrep \"PID|{$search}\" | grep -v grep";
        $exist = (shell_exec($psCmd)) ? true : false;

        if ($exist) {
            
            $psInfo = shell_exec($psInfoCmd);
            die("Skip: Same process `{$action}` is running: {$route}.\n------\n{$psInfo}");
        }

        // Launch by calling command
        $launchCmd = "{$cmd} > {$logPath} &";
        $result = shell_exec($launchCmd);
        $result = shell_exec($psCmd);
        $psInfo = shell_exec($psInfoCmd);
        echo "Success to launch process `{$action}`: {$route}.\nCalled command: {$launchCmd}\n------\n{$psInfo}";

        return;
    }

    /**
     * Action for activating a single listened worker
     * 
     * Single process ensures unique process running, which prevents the same  
     * 
     * The reason which this doesn't use process check method such as `ps`, `pgrep`, is that the
     * process ID or name are unrecognizable as unique for ensuring only one Single process is
     * running.
     *
     * @return void
     */
    public function single($force=false)
    {
        // Pre-work check
        if (!method_exists($this, 'handleSingle'))
            throw new Exception("You need to declare `handleSingle()` method in your worker controller.", 500);

        // Shared lock flag builder
        $lockFile = sys_get_temp_dir() 
            . "/yidas-codeiginiter-queue-worker_" 
            . str_replace('/', '_', $this->router->fetch_directory()) 
            . get_called_class()
            . '.lock';

        // Single check for process uniqueness
        if (!$force && file_exists($lockFile)) {

            $lockData = json_decode(file_get_contents($lockFile), true);
            // Check expires time
            if (isset($lockData['expires_at']) && time() <= $lockData['expires_at']) {
                die("Single is already running: {$lockFile}\n");
            }
        }

        // Start Single - Set identified lock
        // Close Single - Release identified lock
        register_shutdown_function(function() use ($lockFile) {
            @unlink($lockFile);
        });

        // Create lock file
        $this->_singleUpdateLock($lockFile);

        // Call customized worker process, stops till catch false by callback return
        while ($this->handleSingle($this->_staticSingle)) {

            // Sleep if set
            if ($this->singleSleep) {
                sleep($this->singleSleep);
            }

            // Refresh lock file
            $this->_singleUpdateLock($lockFile);
        }
    }

    /**
     * Set static listener object for callback function
     * 
     * This is a optional method with object injection instead of assigning and
     * accessing properties.
     *
     * @param object $object
     * @return self
     */
    protected function setStaticListen($object)
    {
        $this->_staticListen = $object;
        
        return $this;
    }

    /**
     * Set static worker object for callback function
     *  
     * This is a optional method with object injection instead of assigning and
     * accessing properties.
     *
     * @param object $object
     * @return self
     */
    protected function setStaticWork($object)
    {
        $this->_staticWork = $object;
        
        return $this;
    }

    /**
     * Set static single object for callback function
     *  
     * This is a optional method with object injection instead of assigning and
     * accessing properties.
     *
     * @param object $object
     * @return self
     */
    protected function setStaticSingle($object)
    {
        $this->_staticSingle = $object;
        
        return $this;
    }

    /**
     * Single process creates or extends lock file
     * 
     * Extended second bases on sleep time and lock expiration
     *
     * @param string $lockFile
     * @return void
     */
    public function _singleUpdateLock($lockFile)
    {
        $lockData = [
            'pid' => getmypid(),
            'expires_at' => time() + $this->singleSleep + $this->singleLockTimeout,
        ];

        return file_put_contents($lockFile, json_encode($lockData));
    }

    /**
     * Command for creating a worker
     *
     * @param string $workerCmd
     * @param integer $workerCount
     * @return string Command result
     */
    protected function _workerCmd($workerCmd, $workerCount)
    {
        // Shell command builder
        $cmd = "{$workerCmd}/{$workerCount}";
        $cmd = ($this->logPath) ? "{$cmd} >> {$this->logPath}" : $cmd;

        // Process handler
        $process = proc_open("{$cmd} &", self::$_procDescriptorspec, $pipe);
        // Find out worker command's PID
        $status = proc_get_status($process);
        $pid = $status['pid'] + 1;
        // Stack workers
        $this->_pidStack[$workerCount] = $pid;
        // Close
        proc_close($process);

        // Log
        $time = date("Y-m-d H:i:s");
        $this->_log("Queue Listener - Dispatch Worker #{$workerCount} (PID: {$pid})");

        return true;
    }

    /**
     * Log to file
     *
     * @param string $textLine
     * @param string Specified log file path
     * @return integer|boolean The number of bytes that were written to the file, or FALSE on failure.
     */
    protected function _log($textLine, $logPath=null)
    {
        // Return back to console also
        $this->_print($textLine);
        
        $logPath = ($logPath) ? $logPath : $this->logPath;

        if ($logPath)
            return file_put_contents($logPath, $this->_formatTextLine($textLine), FILE_APPEND);
        else
            return false;
    }

    /**
     * Print (echo)
     *
     * @param string $textLine
     * @return void
     */
    protected function _print($textLine)
    {
        echo $this->_formatTextLine($textLine);
    }

    /**
     * Format output text line
     *
     * @param string $textLine
     * @return void
     */
    protected function _formatTextLine($textLine)
    {
        return $textLine = date("Y-m-d H:i:s") . " - {$textLine}" . PHP_EOL;
    }

    /**
     * Check if PID is alive or not
     *
     * @param integer Process ID
     * @return boolean
     */
    protected function _isPidAlive($pid)
    {
        return ((function_exists('posix_getpgid') && posix_getpgid($pid)) || file_exists("/proc/{$pid}")) ? true : false;
    }

    /**
     * Check if OS is Linux 
     *
     * @return boolean
     */
    protected function _isLinux()
    {
        // Just make sure that it's not Windows
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? false : true;
    }

    /**
     * Listener callback function for overriding
     *
     * @param object Listener object for optional
     * @return boolean Return true if has work
     */
    /*
    protected function handleListen($static)
    {
        // Override this method
        
        return false;
    }
    */

    /**
     * Worker callback function for overriding
     *
     * @param object Worker object for optional
     * @return boolean Return false to stop work
     */
    /*
    protected function handleWork($static)
    {
        // Override this method
        
        return false;
    }
    */

    /**
     * Single callback function for overriding
     *
     * @param object Single object for optional
     * @return boolean Return false to stop work
     */
    /*
    protected function handleSingle($static)
    {
        // Override this method
        
        return false;
    }
    */
}
