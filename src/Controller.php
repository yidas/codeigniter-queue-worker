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
     * Time interval of worker processes 
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
     * Static worker object for injecting into customized callback process
     *
     * @var object
     */
    protected $staticWorker;

    /**
     * Static listener object for injecting into customized callback process
     *
     * @var object
     */
    protected $staticListener;
    
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
    public function listener()
    {
        // Pre-work check
        if (!method_exists($this, 'listenerCallback'))
            throw new Exception("You need to declare `listenerCallback()` method in your worker controller.", 500);
        if (!method_exists($this, 'workerCallback'))
            throw new Exception("You need to declare `workerCallback()` method in your worker controller.", 500);
        if ($this->logPath && !file_exists($this->logPath)) {
            if (!$this->_log('')) {
                throw new Exception("Log file doesn't exsit: `{$this->logPath}`.", 500);
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
        $workerAction = 'worker';
        $route = $this->router->fetch_directory() .'/'. $this->router->fetch_class() . "/{$workerAction}";
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
            
            // Call customized listener process, assigns works while catching true by callback return
        	$hasEvent = ($this->listenerCallback($this->staticListener)) ? true : false;

            // Start works if exists
            if ($hasEvent) {

                // First time to assign works
                if (!$workingFlag) {
                    $workingFlag = true;
                    $startTime = microtime(true);
                    $this->_log("Worker Manager start assignment at: " . date("Y-m-d H:i:s") );

                    if ($this->workerStartNum > 1) {
                        // Execute extra worker numbers
                        for ($i=1; $i < $this->workerStartNum ; $i++) { 
                            $workerCount ++;
                            $r = $this->_workerCmd($workerCmd, $workerCount);
                        }
                    }
                }

                // Max running worker numbers check
                if ($this->workerMaxNum <= $workerCount) {
                    sleep($this->listenerSleep);
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
                $costSeconds = number_format(microtime(true) - $startTime, 2, '.', '');
                $this->_log("Worker Manager stop assignment at: " . date("Y-m-d H:i:s") . ", total cost: {$costSeconds}s");
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
    public function worker($id=1)
    {
        // INI setting
        if ($this->debug) {
            error_reporting(-1);
            ini_set('display_errors', 1);
        }
        set_time_limit(0);

        // Start worker
        $startTime = microtime(true);
        $workerTimestamp = date("Y-m-d H:i:s", $startTime);
        // Print worker close
        $this->_print("Worker #{$id} create at: {$workerTimestamp}");

        // Call customized worker process, stops till catch false by callback return
        while ($this->workerCallback($this->staticWorker)) {
            // Sleep if set
            if ($this->workerSleep) {
                sleep($this->workerSleep);
            }
        }

        // Print worker close
        $costSeconds = number_format(microtime(true) - $startTime, 2, '.', '');
        $this->_print("Worker #{$id} close at: " . date("Y-m-d H:i:s") . " | cost: {$costSeconds}s");

        return;
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
    protected function setStaticListener($object)
    {
        $this->staticListener = $object;
        
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
    protected function setStaticWorker($object)
    {
        $this->staticWorker = $object;
        
        return $this;
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

        return shell_exec("{$cmd} &");
    }

    /**
     * Log to file
     *
     * @param string $textLine
     * @return integer|boolean The number of bytes that were written to the file, or FALSE on failure.
     */
    protected function _log($textLine)
    {
        if ($this->logPath)
            return file_put_contents($this->logPath, $textLine . PHP_EOL, FILE_APPEND);
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
        echo $textLine . PHP_EOL;
    }

    /**
     * Listener callback function for overriding
     *
     * @param object Listener object for optional
     * @return boolean Return true if has work
     */
    /*
    protected function listenerCallback($static)
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
    protected function workerCallback($static)
    {
        // Override this method
        
        return false;
    }
    */
}
