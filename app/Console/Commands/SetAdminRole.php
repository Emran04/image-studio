<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:set-admin-role {email : Existing account email} {--revoke : Remove admin access}')]
#[Description('Grant or revoke admin access for an existing account')]
class SetAdminRole extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first()
            ?? User::where('email', Str::lower($email))->first();

        if ($user === null) {
            $this->error('No account found for that email.');

            return self::FAILURE;
        }

        $isAdmin = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $isAdmin])->save();
        $this->info($isAdmin ? 'Admin access granted.' : 'Admin access revoked.');

        return self::SUCCESS;
    }
}
