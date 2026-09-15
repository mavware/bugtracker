<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote {email : The email address of the user} {--role=admin : The role to grant: admin or professional} {--demote : Make the account a plain homeowner instead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set an account\'s role. Needed once to create the first admin.';

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

        $role = (bool) $this->option('demote')
            ? UserRole::Homeowner
            : UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('The role must be one of: '.implode(', ', array_column(UserRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info(match ($role) {
            UserRole::Admin => "$user->email is now a site admin.",
            UserRole::Professional => "$user->email is now a professional.",
            UserRole::Homeowner => "$user->email is no longer a site admin or professional; the account is a homeowner.",
        });

        return self::SUCCESS;
    }
}
