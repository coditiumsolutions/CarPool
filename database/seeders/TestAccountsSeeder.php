<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Makes the documented demo accounts log in with a known password.
 * Only touches the accounts below; never creates admins. Refuses to run in production
 * unless RIDE_ALLOW_TEST_ACCOUNTS=true (staging / demo servers).
 *
 *   php artisan db:seed --class=TestAccountsSeeder
 *
 * Password for all of them: Password123
 */
class TestAccountsSeeder extends Seeder
{
    public const PASSWORD = 'Password123';

    public function run(): void
    {
        if (app()->isProduction() && ! env('RIDE_ALLOW_TEST_ACCOUNTS', false)) {
            $this->command?->error('Refusing to seed test accounts in production. Set RIDE_ALLOW_TEST_ACCOUNTS=true to override.');

            return;
        }

        $accounts = [
            ['ali@ride.com', '03000000002', 'Ali Ahmed', User::TYPE_DRIVER],
            ['sara@ride.com', '03000000003', 'Sara Khan', User::TYPE_PASSENGER],
            ['usman@ride.com', '03000000004', 'Usman Malik', User::TYPE_BOTH],
            ['bilal@ride.com', '03000000005', 'Bilal Hussain', User::TYPE_DRIVER],
        ];

        foreach ($accounts as [$email, $phone, $name, $type]) {
            $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
            $user->forceFill([
                'name' => $user->name ?: $name,
                'phone' => $user->phone ?: $phone,
                'user_type' => $user->exists ? $user->user_type : $type,
                'status' => 'active',
                'is_verified' => true,
                'password' => self::PASSWORD, // hashed by the model cast
            ])->save();
        }

        $this->command?->info('Test accounts ready. Password: '.self::PASSWORD);
    }
}
