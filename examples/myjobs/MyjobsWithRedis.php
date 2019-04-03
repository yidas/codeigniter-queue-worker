<?php

/**
 * myjobs sample code using Redis
 * 
 * This sample library mixed Redis client connection, which you can separate into components in
 * actual development.
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 * @package https://github.com/nrk/predis
 */
class MyjobsWithRedis
{
    /**
     * Redis key
     */
    const QUEUE_KEY = 'your-job-key';
    
    /**
     * Redis client
     *
     * @var Predis\Client
     */
    protected $redisClient;
    
    function __construct() 
    {
        // You can store Redis config into application config, for example: `$this->CI->config->item('redis', 'services');`
        $this->redisClient = new Predis\Client(['host'=>'yourHostOrIP', 'scheme'=>'tcp', 'port'=>6379], ['parameters'=>['password'=>'yourpass']]);

        // Connection check
        try {

            $this->redisClient->type('test');

        } catch (Predis\Connection\ConnectionException $e) {

            if (ENVIRONMENT=='development') {
                throw $e;
            }
            // Prevent further error
            exit;
        }
    }

    /**
     * Check if there are any jobs from Redis queue
     *
     * @return boolean
     */
    public function exists()
    {
        return $this->redisClient->exists(self::QUEUE_KEY);
    }

    /**
     * Pop up a job from Redis queue
     *
     * @return array
     */
    public function popJob()
    {
        // Assume storing JSON string data in queue
        // Using LPOP or RPOP depends on your producer push
        $taskJSON = $this->redisClient->lpop(self::QUEUE_KEY);

        return json_decode($taskJSON, true);
    }

    /**
     * Process a job
     *
     * @param array $job
     * @return boolean
     */
    public function processJob($job)
    {
        // Your own job process here

        return true;
    }
}
