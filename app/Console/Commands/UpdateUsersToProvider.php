<?php

namespace App\Console\Commands;

use App\Models\Queue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UpdateUsersToProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-users-to-provider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $usersToUpdate = User::join('queues', 'users.id', '=', 'queues.user_id')->get();

        if (sizeof($usersToUpdate) < 300) {
            return;
        }

        // get remaining batch requests for the hour if set, using the date and hour as key
        $batchRequestsRemaining = Cache::get('batch-requests-remaining-' . Carbon::now()->format('d/m/y h'));

        if (!$batchRequestsRemaining) {
            $batchRequestsRemaining = 50;
        }

        if ($batchRequestsRemaining > 0) {
            $this->executeBatchRequest($batchRequestsRemaining, $usersToUpdate);
        } else {
            // handle with single requests if no batch requests left
            
            $singleRequestsRemaining = Cache::get('single-requests-remaining-' . Carbon::now()->format('d/m/y h'));

            if (!$singleRequestsRemaining) {
                $singleRequestsRemaining = 3600;
            }

            if ($singleRequestsRemaining > 0) {
                $this->executeSingleRequest($singleRequestsRemaining, $usersToUpdate);
            }
        }

        logger('Done!');
    }

    private function executeBatchRequest($batchRequestsRemaining, $usersToUpdate) {
        if (sizeof($usersToUpdate) >= 2000) {
            $usersToUpdateChunks = array_chunk($usersToUpdate, 1000);

            foreach ($usersToUpdateChunks as $chunkIndex => $usersChunk) {

                // check if we can cover all chunks with the remaining batch requests
                if ($chunkIndex + 1 > $batchRequestsRemaining) {
                    break;
                }

                $this->createBatchAndRun($usersChunk, $batchRequestsRemaining);
            }
        } else {
            $this->createBatchAndRun($usersToUpdate, $batchRequestsRemaining);
        }
    }

    private function createBatchAndRun($usersToUpdate, $batchRequestsRemaining) {
        $usersToDeleteFromQueue = [];
        $requestBody = [
            "batches" => [
                "subscribers" => []
            ]
        ];

        foreach ($usersToUpdate as $user) { 
            $requestBody['batches']['subscribers'][] = [
                "email" => $user->email,
                "name" => $user->name,
                "time_zone" => $user->timezone
            ];

            $usersToDeleteFromQueue[] = $user->id;
        }

        // simulating api call, I'm redoing the for just to separate the log from the batch creation
        foreach ($requestBody['batches']['subscribers'] as $index => $user) {
            logger('[' . $index . '] firstname:' . $user['name'] . ' timezone:' . $user['timezone']);
        }

        Queue::whereIn('id', $usersToDeleteFromQueue)->delete();

        Cache::set('batch-requests-remaining-' . Carbon::now()->format('d/m/y h'), $batchRequestsRemaining--);
    }

    private function executeSingleRequest($singleRequestsRemaining, $usersToUpdate) {
        foreach ($usersToUpdate as $index => $user) {
            
            // check if we can cover all users with the remaining single requests
            if ($index + 1 > $singleRequestsRemaining) {
                logger('No more requests, batch or single, for this hour: ' . Carbon::now()->format('d/m/y h') . '!');
                break;
            }

            $requestBody = [
                "email" => $user->email,
                "name" => $user->name,
                "time_zone" => $user->timezone
            ];

            logger('[' . $index . '] firstname:' . $requestBody['name'] . ' timezone:' . $requestBody['time_zone']);

            Queue::where('user_id', $user->id)->delete();
        }

        Cache::set('single-requests-remaining-' . Carbon::now()->format('d/m/y h'), $singleRequestsRemaining - sizeof($usersToUpdate));
    }
}
