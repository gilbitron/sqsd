<?php

namespace Sqsd;

use Cron\CronExpression;
use GuzzleHttp\Client;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Sqsd
{
    /**
     * @var Options
     */
    protected $options;
    /**
     * @var Sqs
     */
    protected $sqs;
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Sqsd constructor.
     *
     * @param Options $options
     * @param Sqs $sqs
     * @param OutputInterface $output
     */
    public function __construct(Options $options, Sqs $sqs, OutputInterface $output)
    {
        $this->options = $options;
        $this->sqs     = $sqs;
        $this->output  = $output;
    }

    /**
     * Handle SQS queue messages
     */
    public function checkForMessages()
    {
        $promise = $this->sqs->recieveMessage();

        $promise->then(function ($result) {
            $messages = $result->get('Messages');

            if (count($messages)) {
                $this->postMessagesToUrl($messages);
            }
        }, function ($reason) {
            $this->output->writeln('<error>' . $reason . '</error>');
        });

        $promise->wait();
    }

    /**
     * Post a SQS message to the worker URL
     *
     * @param array $messages
     */
    protected function postMessagesToUrl($messages)
    {
        $guzzle = new Client();

        echo 'Processing ' . number_format(count($messages)) . " messages...\n";

        foreach ($messages as $message) {
            $workerPath = $this->options->workerPath;
            if (isset($message['MessageAttributes']['beanstalk.sqsd.path']['StringValue'])) {
                $workerPath = $message['MessageAttributes']['beanstalk.sqsd.path']['StringValue'];
            }

            $this->output->writeln('POSTing to ' . $this->options->workerUrl . $workerPath);

            $response = $guzzle->request('POST', $this->options->workerUrl . $workerPath, [
                'headers' => $this->collectMessageHeaders($message, $this->options->sqsQueueName),
                'body'    => $message['Body'],
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->sqs->deleteMessage($message['ReceiptHandle'])->then(function ($result) use ($message) {
                    $this->output->writeln('Message successfully processed ' . $message['MessageId']);
                }, function ($reason) {
                    $this->output->writeln('<error>' . $reason . '</error>');
                });
            } else {
                $this->output->writeln('<error>Error response from worker URL: ' . $response->getStatusCode() .
                                       '</error>');
            }
        }
    }

    /**
     * Return the headers required for the POST to the worker URL
     *
     * @param array $message
     * @param string $sqsQueueName
     * @return array
     */
    protected function collectMessageHeaders($message, $sqsQueueName)
    {
        $headers = [
            'Content-Type'     => 'application/json',
            'User-Agent'       => 'aws-sqsd',
            'X-Aws-Sqsd-Msgid' => $message['MessageId'],
            'X-Aws-Sqsd-Queue' => $sqsQueueName,
        ];

        if (isset($message['Attributes']['ApproximateFirstReceiveTimestamp'])) {
            $headers['X-Aws-Sqsd-First-Received-At'] = $message['Attributes']['ApproximateFirstReceiveTimestamp'];
        }

        if (isset($message['Attributes']['ApproximateReceiveCount'])) {
            $headers['X-Aws-Sqsd-Receive-Count'] = $message['Attributes']['ApproximateReceiveCount'];
        }

        if (isset($message['Attributes']['SenderId'])) {
            $headers['X-Aws-Sqsd-Sender-Id'] = $message['Attributes']['SenderId'];
        }

        if (isset($message['MessageAttributes'])) {
            foreach ($message['MessageAttributes'] as $messageAttribute) {
                foreach ($messageAttribute as $messageAttributeName => $messageAttributeValues) {
                    if (!isset($messageAttributeValues['DataType']) ||
                        $messageAttributeValues['DataType'] == 'Binary'
                    ) {
                        continue;
                    }

                    $value = '';

                    if (isset($messageAttributeValues['StringListValues'])) {
                        $value = implode(',', $messageAttributeValues['StringListValues']);
                    }
                    if (isset($messageAttributeValues['StringValue'])) {
                        $value = $messageAttributeValues['StringValue'];
                    }

                    $headers['X-Aws-Sqsd-Attr-' . $messageAttributeName] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Run periodic tasks defined in cron.yaml
     */
    public function runPeriodicTasks()
    {
        if (!$this->options->cronPath) {
            return;
        }

        $yaml = $this->parseCronYaml();

        foreach ($yaml['cron'] as $cron) {
            $this->handleCron($cron);
        }
    }

    /**
     * Parse the cron.yaml file
     *
     * @return array
     * @throws \Exception
     */
    protected function parseCronYaml()
    {
        if (!file_exists($this->options->cronPath) || basename($this->options->cronPath) != 'cron.yaml') {
            throw new \Exception('Invalid path to cron.yaml: ' . $this->options->cronPath);
        }

        $yaml = Yaml::parse(file_get_contents($this->options->cronPath));
        if (!isset($yaml['cron']) || empty($yaml['cron'])) {
            throw new \Exception('No crons found in cron.yaml');
        }

        return $yaml;
    }

    /**
     * Handle a cron
     *
     * @param array $cron
     */
    protected function handleCron($cron)
    {
        if (!isset($cron['name'], $cron['url'], $cron['schedule'])) {
            return;
        }

        if (!is_dir($this->options->storagePath)) {
            mkdir($this->options->storagePath, 0777, true);
        }

        $fileName = preg_replace('/[^a-zA-Z0-9\-\._]/', '', $cron['name']);
        $filePath = $this->options->storagePath . '/' . $fileName . '.cron';

        if (file_exists($filePath)) {
            $timestamp = file_get_contents($filePath);
        } else {
            $timestamp = strtotime('-1 min');
        }

        if (time() >= $timestamp) {
            $this->sendCronToSqs($cron, date('Y-m-d H:i:s T', $timestamp));

            $cronExpression = CronExpression::factory($cron['schedule']);
            file_put_contents($filePath, $cronExpression->getNextRunDate()->getTimestamp());
        }
    }

    /**
     * Add cron job to SQS queue
     *
     * @param array cron
     * @param string $scheduledAt
     */
    protected function sendCronToSqs($cron, $scheduledAt)
    {
        $this->sqs->sendMessage([
            'MessageBody'       => 'elasticbeanstalk scheduled job',
            'MessageAttributes' => [
                'beanstalk.sqsd.path'           => [
                    'DataType'    => 'String',
                    'StringValue' => $cron['url'],
                ],
                'beanstalk.sqsd.scheduled_time' => [
                    'DataType'    => 'String',
                    'StringValue' => $scheduledAt,
                ],
                'beanstalk.sqsd.task_name'      => [
                    'DataType'    => 'String',
                    'StringValue' => $cron['name'],
                ],
            ],
        ])->then(function ($result) {
            $this->output->writeln('Message successfully sent ' . $result['MessageId']);
        }, function ($reason) {
            $this->output->writeln('<error>' . $reason . '</error>');
        });
    }
}