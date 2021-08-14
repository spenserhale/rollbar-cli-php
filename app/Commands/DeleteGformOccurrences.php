<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class DeleteGformOccurrences extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'task:delete-form-occurrences {offset=0}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Queries to find and then deletes form occurrences';

    protected $jobs;

    protected $occurrences;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->jobs = collect();

        $daysCount = Carbon::parse('2015-00-00 00:00:00')->diffInDays(Carbon::now());

        for ($day = $this->argument('offset'); $day < $daysCount; $day++) {
            $job = [];

            $jobCreated = false;
            while(!$jobCreated) {
                $jobCreated = $this->task("Create Job for Day $day", function() use($day, &$job) {
                    $dayPlusOne = $day + 1;
                    $sql = <<<SQL
SELECT *
FROM item_occurrence
WHERE `request.POST.gform_unique_id`= ''
AND unix_timestamp() - timestamp BETWEEN 60 * 60 * 24 * $day and 60 * 60 * 24 * $dayPlusOne
LIMIT 1000
SQL;
                    $response = Http::withHeaders([
                        'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
                    ])->post('https://api.rollbar.com/api/1/rql/jobs/', [
                        'query_string' => $sql,
                        'force_refresh' => true,
                    ]);

                    $error = $response->json('err');
                    if($error) {
                        $this->error($response->json('message'));
                        return false;
                    }

                    $job = $response->json('result');
                    return true;
                });
                if(!$jobCreated) {
                    sleep(5);
                }
            }

            $this->task("Wait for Job {$job['id']}", function () use ($job) {
                $statusDone = false;
                while (!$statusDone) {
                    $response = Http::withHeaders([
                        'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
                    ])->get("https://api.rollbar.com/api/1/rql/job/{$job['id']}");

                    $error = $response->json('err');
                    if ($error) {
                        $this->error($response->json('message'));
                        return false;
                    }

                    $result = $response->json('result');

                    $statusDone = $result['status'] === 'success';
                    if (!$statusDone) {
                        sleep(2);
                    }
                }

                return true;
            });

            $results = [];
            $this->task("Get results for Job {$job['id']}", function() use ($job, &$results) {
                $response = Http::withHeaders([
                    'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
                ])->get("https://api.rollbar.com/api/1/rql/job/{$job['id']}/result");

                $error = $response->json('err');
                if ($error) {
                    $this->error($response->json('message'));
                    return false;
                }

                $result = $response->json('result');
                $results = $result['result'];

                return true;
            });

            if($results['rowcount'] === 0) {
                continue;
            }

            $rows = $results['rows'];
            $occurrence_key = array_search('occurrence_id', $results['columns'], true);
            if(empty($occurrence_key)) {
                $this->error('no occurrence key found');
                dd($results);
            }

            foreach ($rows as $row) {
                $occurrence_id = $row[$occurrence_key];
                $this->task("Delete occurrence $occurrence_id", function() use($occurrence_id) {
                    $response = Http::withHeaders([
                        'X-Rollbar-Access-Token' => env('ROLLBAR_ACCESS_TOKEN'),
                    ])->delete("https://api.rollbar.com/api/1/instance/$occurrence_id");

                    $error = $response->json('err');
                    if ($error) {
                        $this->error($response->json('message'));
                        return false;
                    }

                    return true;
                });
            }
        }

        $this->info('Success');
        return true;
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

    public function error($string, $verbosity = null)
    {
        $this->newLine();
        parent::error($string, $verbosity);
    }
}
