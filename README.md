# SQSD

While testing a [Laravel](https://laravel.com) app running on [AWS Elastic Beanstalk](https://aws.amazon.com/elasticbeanstalk/) I came across the situation where I wanted to be able to test how my app would handle interacting with the worker tier SQS daemon (`sqsd`) on my local machine. As `sqsd` is not open source there is currently no official way of doing this.

This project ([inspired by others](https://github.com/proofme/sqsd)) is an attempt to replicate the functionality of `sqsd` in PHP for local testing purposes. Note that his library has **no dependency on Laravel** and can be used to test any kind of app.

## Description

![Sqsd architecture](https://cloud.githubusercontent.com/assets/203882/25480291/1719d8ca-2b40-11e7-8a2c-37831e59559c.png)

The Laravel queue worker operates by polling a queue for jobs and then runs them inline (as the queue worker is part of the application). As `sqsd` is completely separate from any application it works in a different way so that any application can be designed to work with it.

Sqsd works by polling a SQS queue for jobs and then `POST`'s them to an endpoint specified in your [Elastic Beanstalk worker environment settings](http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html#using-features-managing-env-tiers-worker-settings) (default is `http://localhost/`). If a job fails for any reason, then the job is sent to what is called a [Dead Letter Queue](http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html#worker-deadletter) for manual processing.

Another aspect of `sqsd` is that it can read a `cron.yaml` file in the root of your application which specifies [periodic tasks](http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html#worker-periodictasks) that can be run on a schedule. Sqsd will send a job to the queue every time the schedule is triggered. These jobs will then be processed by `sqsd` like normal, but will be `POST`ed to the path specified in the `cron.yaml` with some extra headers.

## Requirements

* PHP >= 5.5.0
* Composer

## Install & Usage

1. Clone this repo
1. Copy `.env.example` to `.env` and fill in the details (see below)
1. Run `composer install`
1. Run `php sqsd work`

## Configuration

Configuration is done by either setting environment variables before running `sqsd` or specifying them in `.env`.

| Env Variable | Description |
| --- | --- |
| `AWS_ACCESS_KEY_ID` * | AWS access key ID with SQS permissions |
| `AWS_SECRET_ACCESS_KEY` * | AWS secret access key |
| `SQS_QUEUE_URL` * | The URL of the SQS queue (e.g. `https://sqs.us-east-1.amazonaws.com/123456789`) |
| `SQS_QUEUE_NAME` * | The name of the SQS queue |
| `SQS_QUEUE_REGION` * | The region of the SQS queue (e.g. `us-east-1`) |
| `SQS_MAX_MESSAGES` | The maximum number of messages that will be received from the SQS queue at a time (default and max is `10`) |
| `SQS_WAIT_TIME` | The length of time in seconds that `sqsd` will wait while polling the SQS queue for messages |
| `SQSD_WORKER_URL` | The URL the worker will `POST` to (default is `http://localhost`) |
| `SQSD_WORKER_PATH` | The path that will be appended to the worker URL (default is `/`). This is the same as the **HTTP Path** setting in the [Elastic Beanstalk worker environment settings](http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html#using-features-managing-env-tiers-worker-settings) |
| `SQSD_CRON_PATH` | Absolute path to a `cron.yaml` which specifies periodic tasks |
| `SQSD_SLEEP_SECONDS` | The time in seconds `sqsd` should sleep between checks (default is `1`) |

Environment variables marked with a * are required for `sqsd` to work.

## Laravel Tip

If you are using this to test a Laravel app, instead of [configuring Supervisor](https://laravel.com/docs/5.4/queues#supervisor-configuration) to run the `aritsan queue:work` command, configure Supervisor to run the `sqsd work` command.

## Credits

Sqsd was created by [Gilbert Pellegrom](http://gilbert.pellegrom.me) from
[Dev7studios](http://dev7studios.co). Released under the MIT license.
