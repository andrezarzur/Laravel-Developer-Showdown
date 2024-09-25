<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateUsersDataRandomly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-users-data-randomly';

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
        $users = User::all();

        foreach ($users as $user) {
            $user->update([
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'timezone' => fake()->randomElement(['CET', 'CST', 'GMT+1']),
            ]);
        }
    }
}
