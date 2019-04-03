<?php

/**
 * myjobs sample code using database for single worker
 * 
 * This sample library uses database table records as a queue, we delete `exist()` and `popJob()` 
 * methods and add a `getJob()` to implement the database characteristics for single worker usage.
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 */
class MyjobsWithDatabase
{
    function __construct() 
    {
        $this->CI = & get_instance();
        $this->CI->load->model("job_queue_model");
    }

    /**
     * Get a undo job from database table
     *
     * @return array
     */
    public function getJob()
    {
        return $this->CI->job_queue_model->getJob();
    }

    /**
     * Process then delete a job
     *
     * @param array $job
     * @return boolean
     */
    public function processJob($job)
    {
        // Your own job process here

        // Delete job record after finishing process
        return $this->CI->job_queue_model->deleteJob($job['id']);
    }
}
