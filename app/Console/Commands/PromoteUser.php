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
    protected $signature = 'user:promote {email : The email address of the user} {--role=admin : The role to grant: admin, professional or homeowner} {--demote : Take the role away instead of granting it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant or revoke one of an account\'s roles. Needed once to create the first admin.';

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

        $role = UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('The role must be one of: '.implode(', ', array_column(UserRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $demote = (bool) $this->option('demote');

        $demote ? $user->revokeRole($role) : $user->grantRole($role);
        $user->save();

        $label = strtolower($role->label());
        $held = $user->roles->map(fn (UserRole $held): string => strtolower($held->label()))->implode(', ');

        $this->info($demote
            ? "$user->email is no longer a $label. Roles now: ".($held !== '' ? $held : 'none').'.'
            : "$user->email is now a $label. Roles now: $held.");

        return self::SUCCESS;
    }
}
