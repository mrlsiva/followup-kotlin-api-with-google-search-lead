<?php

namespace Database\Seeders;

use App\Enums\CallStatus;
use App\Models\User;
use App\Services\FollowupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data: login admin@example.com / password
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(FollowupService $followups): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Sales Admin', 'password' => Hash::make('password')]
        );

        $leads = [
            ['Ravi Kumar', '+91 98765 43210', 'ravi@acmetraders.in', 'Acme Traders', 'WhatsApp', 'Interested', 'High', 'Needs quote for 200 units', today()->setTime(11, 0)],
            ['Priya Sharma', '+91 99887 76655', 'priya@brightsolar.com', 'Bright Solar', 'Website', 'New', 'Medium', 'Enquiry from website form', today()->setTime(16, 30)],
            ['Arun Prakash', '+91 90030 11223', null, 'AP Constructions', 'Referral', 'Follow-up', 'High', 'Referred by Ravi', now()->subDay()->setTime(10, 0)],
            ['Meena Raj', '+91 94440 55667', 'meena@freshmart.in', 'FreshMart', 'Facebook', 'Interested', 'Low', null, now()->addDays(2)->setTime(12, 0)],
            ['Karthik S', '+91 97890 12345', null, null, 'Walk-in', 'Won', 'Medium', 'Closed deal, onboarding next week', null],
            ['Divya N', '+91 93600 98765', 'divya@studio9.co', 'Studio 9', 'Call', 'Lost', 'Low', 'Went with competitor', null],
        ];

        foreach ($leads as [$name, $mobile, $email, $company, $source, $status, $priority, $notes, $next]) {
            $lead = $user->leads()->updateOrCreate(['mobile' => $mobile], compact('name', 'email', 'company', 'source', 'status', 'priority', 'notes'));
            $followups->syncNextFollowup($lead, $next ? Carbon::parse($next) : null);
        }

        $ravi = $user->leads()->where('name', 'Ravi Kumar')->first();
        if ($ravi->followups()->completed()->doesntExist()) {
            $followups->addNote($ravi, ['at' => now()->subDays(3)->setTime(15, 0), 'note' => 'First call, shared catalogue', 'call_status' => CallStatus::Interested, 'next_at' => null, 'schedule_next' => null]);
            $followups->addNote($ravi, ['at' => now()->subDay()->setTime(12, 15), 'note' => 'Did not pick up', 'call_status' => CallStatus::NotAnswered, 'next_at' => null, 'schedule_next' => null]);
        }
    }
}
