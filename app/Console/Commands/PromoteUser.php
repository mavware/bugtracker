<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote {email : The email address of the user} {--demote : Take admin access away instead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant or revoke site admin access. Needed once to create the first admin.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user with the email $email.");

            return self::FAILURE;
        }

        $demoting = (bool) $this->option('demote');

        $user->is_admin = ! $demoting;
        $user->save();

        $this->info($demoting
            ? "$user->email is no longer a site admin."
            : "$user->email is now a site admin.");

        return self::SUCCESS;
    }
}
