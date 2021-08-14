<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Redis;

class GetOccurance extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'instance:get {id}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Get RQL Job';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $id = $this->argument('id');

        $response = Http::withHeaders([
            'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
        ])->get("https://api.rollbar.com/api/1/instance/$id");

        $error = $response->json('err');
        $result = $response->json('result');
        $message = $response->json('message');

        dd(compact('error', 'result', 'message'));
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
