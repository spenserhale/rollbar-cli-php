<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Redis;

class CreateRqlJob extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'rql:create';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create RQL Job';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sql = <<<SQL
SELECT *
FROM item_occurrence
WHERE `request.POST.gform_unique_id`= ''
AND unix_timestamp() - timestamp BETWEEN 60 * 60 * 24 * 0 and 60 * 60 * 24 * 1
SQL;
        $response = Http::withHeaders([
            'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
        ])->post('https://api.rollbar.com/api/1/rql/jobs/', [
            'query_string' => $sql,
        ]);

        $error = $response->json('err');
        $result = $response->json('result');

        dd(compact('error', 'result'));
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
