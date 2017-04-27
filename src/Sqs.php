<?php

namespace Sqsd;

use Aws\Sqs\SqsClient;

class Sqs
{
    /**
     * @var Options
     */
    protected $options;
    /**
     * @var SqsClient|null
     */
    private $client;

    /**
     * Sqs constructor.
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->client  = null;
    }

    /**
     * Receive message(s) from the SQS queue
     *
     * @return \GuzzleHttp\Promise\Promise
     */
    public function recieveMessage()
    {
        $client = $this->getClient();

        return $client->receiveMessageAsync([
            'AttributeNames'        => ['All'],
            'MaxNumberOfMessages'   => $this->options->sqsMaxMessages,
            'MessageAttributeNames' => ['All'],
            'QueueUrl'              => $this->options->sqsQueueUrl . '/' . $this->options->sqsQueueName,
            'WaitTimeSeconds'       => $this->options->sqsWaitTime,
        ]);
    }

    /**
     * Delete a message from the SQS queue
     *
     * @param string $receiptHandle
     * @return \GuzzleHttp\Promise\Promise
     */
    public function deleteMessage($receiptHandle)
    {
        $client = $this->getClient();

        return $client->deleteMessageAsync([
            'QueueUrl'      => $this->options->sqsQueueUrl . '/' . $this->options->sqsQueueName,
            'ReceiptHandle' => $receiptHandle,
        ]);
    }

    /**
     * Send a message to the SQS queue
     *
     * @param string $message
     * @return \GuzzleHttp\Promise\Promise
     */
    public function sendMessage($message)
    {
        $client = $this->getClient();

        $message = array_merge([
            'QueueUrl' => $this->options->sqsQueueUrl . '/' . $this->options->sqsQueueName,
        ], $message);

        return $client->sendMessageAsync($message);
    }

    /**
     * Returns an instance of a SqsClient
     *
     * @return SqsClient
     */
    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new SqsClient([
                'version'     => 'latest',
                'region'      => $this->options->sqsQueueRegion,
                'credentials' => [
                    'key'    => $this->options->awsAccessKeyId,
                    'secret' => $this->options->awsSecretAccessKey,
                ],
            ]);
        }

        return $this->client;
    }
}