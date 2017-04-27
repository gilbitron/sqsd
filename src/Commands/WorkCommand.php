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

        $options = new Options();
        $worker  = new Worker($options);
        $sqs     = new Sqs($options);
        $sqsd    = new Sqsd($options, $sqs, $output);

        $output->writeln('<info>Listening for SQS messages from ' . $options->sqsQueueName . '...</info>');

        if (!$options->cronPath) {
            $output->writeln('No cron.yaml specified');
        }

        return $worker->daemon(function () use ($sqsd) {
            $sqsd->runPeriodicTasks();
            $sqsd->checkForMessages();
        });
    }
}