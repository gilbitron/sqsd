<?php

namespace Sqsd;

class Options
{
    /**
     * @var string
     */
    public $awsAccessKeyId;
    /**
     * @var string
     */
    public $awsSecretAccessKey;
    /**
     * @var string
     */
    public $sqsQueueUrl;
    /**
     * @var string
     */
    public $sqsQueueName;
    /**
     * @var string
     */
    public $sqsQueueRegion;
    /**
     * @var int
     */
    public $sqsMaxMessages;
    /**
     * @var int
     */
    public $sqsWaitTime;
    /**
     * @var string
     */
    public $workerUrl;
    /**
     * @var string
     */
    public $workerPath;
    /**
     * @var string
     */
    public $cronPath;
    /**
     * @var int
     */
    public $sleep;
    /**
     * @var string
     */
    public $storagePath;

    /**
     * Options constructor.
     */
    public function __construct()
    {
        $this->awsAccessKeyId     = $this->env('AWS_ACCESS_KEY_ID');
        $this->awsSecretAccessKey = $this->env('AWS_SECRET_ACCESS_KEY');
        $this->sqsQueueUrl        = $this->env('SQS_QUEUE_URL');
        $this->sqsQueueName       = $this->env('SQS_QUEUE_NAME');
        $this->sqsQueueRegion     = $this->env('SQS_QUEUE_REGION');
        $this->sqsMaxMessages     = $this->env('SQS_MAX_MESSAGES', 10);
        $this->sqsWaitTime        = $this->env('SQS_WAIT_TIME', 20);
        $this->workerUrl          = $this->env('SQSD_WORKER_URL', 'http://localhost');
        $this->workerPath         = $this->env('SQSD_WORKER_PATH', '/');
        $this->cronPath           = $this->env('SQSD_CRON_PATH');
        $this->sleep              = $this->env('SQSD_SLEEP_SECONDS', 1);
        $this->storagePath        = $this->env('SQSD_STORAGE_PATH', '/tmp/sqsd');
    }

    /**
     * Get the value of an environment variable
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    private function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        return $value;
    }
}