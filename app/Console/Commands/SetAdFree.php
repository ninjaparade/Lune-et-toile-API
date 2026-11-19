<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:ad-free {username : The player\'s username} {--off : Show them ads again}')]
#[Description('Turn ads off (or back on) for one player, e.g. a supporter or a friend')]
class SetAdFree extends Command
{
    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();
        if (! $user) {
            $this->error('No player with that username.');

            return self::FAILURE;
        }

        $user->forceFill(['ad_free' => ! $this->option('off')])->save();
        $this->info("@{$user->username} ".($user->ad_free ? 'is now ad-free.' : 'will see ads again.'));

        return self::SUCCESS;
    }
}
