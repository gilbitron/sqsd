<?php

namespace Sqsd\Commands;

use Sqsd\Sqs;
use Sqsd\Sqsd;
use Sqsd\Worker;
use Sqsd\Options;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkCommand extends Command
{
    protected function configure()
    {
        $this->setName('work')->setDescription('Run the SQS daemon');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (file_exists(BASE_PATH . '/.env')) {
            $dotenv = new \Dotenv\Dotenv(BASE_PATH);
            $dotenv->load();
        }

        $this->checkRequiredEnvironmentVars();

        $options = new Options();
        $worker  = new Worker($options);
        $sqs     = new Sqs($options);
        $sqsd    = new Sqsd($options, $sqs, $output);

        $output->writeln('<info>Listening for SQS messages from ' . $options->sqsQueueName . '...</info>');

        if (!$options->cronPath) {
            $output->writeln('No cron.yaml specified');
        }

        return $worker->daemon(function () use ($sqsd, $worker, $output) {
            if ($worker->isLeader) {
                $sqsd->runPeriodicTasks();
            }

            $sqsd->checkForMessages();
        });
    }

    /**
     * Check that all required environment variables are set
     *
     * @throws \Exception
     */
    protected function checkRequiredEnvironmentVars()
    {
        $requiredEnvVars = [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'SQS_QUEUE_URL',
            'SQS_QUEUE_NAME',
            'SQS_QUEUE_REGION',
        ];

        foreach ($requiredEnvVars as $requiredEnvVar) {
            if (getenv($requiredEnvVar) === false) {
                throw new \Exception('Missing required environment variable: ' . $requiredEnvVar);
            }
        }
    }
}