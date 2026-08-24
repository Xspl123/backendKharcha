<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->first()->id;
        $now    = Carbon::now();

        // ── 1. Campaigns ──────────────────────────────────
        $campaigns = [
            [
                'user_id'     => $userId,
                'name'        => 'India Export Push Q1 2026',
                'type'        => 'domestic',
                'status'      => 'active',
                'description' => 'Natural extract exporters ko target karna — domestic B2B',
                'start_date'  => '2026-01-01',
                'end_date'    => '2026-03-31',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'user_id'     => $userId,
                'name'        => 'Middle East Expansion 2026',
                'type'        => 'international',
                'status'      => 'active',
                'description' => 'UAE, Saudi Arabia aur Qatar mein B2B leads',
                'start_date'  => '2026-02-01',
                'end_date'    => '2026-06-30',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'user_id'     => $userId,
                'name'        => 'Server Product Campaign',
                'type'        => 'product',
                'status'      => 'draft',
                'description' => 'Dell/HP server products ke liye IT companies ko target karna',
                'start_date'  => '2026-04-01',
                'end_date'    => '2026-06-30',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        DB::table('campaigns')->insert($campaigns);
        $campaignIds = DB::table('campaigns')->where('user_id', $userId)->pluck('id')->toArray();

        // ── 2. Leads ──────────────────────────────────────
        $leads = [
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'TechCorp India Pvt Ltd',
                'contact_person'      => 'Rajesh Kumar',
                'phone'               => '+91 9876543210',
                'email'               => 'rajesh@techcorp.in',
                'website'             => 'https://techcorp.in',
                'country'             => 'India',
                'city'                => 'Mumbai',
                'source'              => 'indiamart',
                'product_interest'    => 'Dell PowerEdge Server',
                'budget'              => 500000,
                'currency'            => 'INR',
                'notes'               => '2 servers chahiye — data center upgrade ke liye',
                'status'              => 'quotation_sent',
                'lost_reason'         => null,
                'expected_close_date' => '2026-04-15',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(15),
                'updated_at'          => $now->copy()->subDays(2),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Al Noor Trading LLC',
                'contact_person'      => 'Mohammed Al Rashid',
                'phone'               => '+971 501234567',
                'email'               => 'mohammed@alnoor.ae',
                'website'             => 'https://alnoor.ae',
                'country'             => 'UAE',
                'city'                => 'Dubai',
                'source'              => 'referral',
                'product_interest'    => 'Natural Extracts — Bulk Order',
                'budget'              => 250000,
                'currency'            => 'USD',
                'notes'               => 'Monthly bulk order requirement. Very serious buyer.',
                'status'              => 'positive_response',
                'lost_reason'         => null,
                'expected_close_date' => '2026-04-01',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(20),
                'updated_at'          => $now->copy()->subDays(1),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Sharma Enterprises',
                'contact_person'      => 'Priya Sharma',
                'phone'               => '+91 8765432109',
                'email'               => 'priya@sharmaenterprises.com',
                'website'             => null,
                'country'             => 'India',
                'city'                => 'Delhi',
                'source'              => 'cold_call',
                'product_interest'    => 'Networking Equipment',
                'budget'              => 150000,
                'currency'            => 'INR',
                'notes'               => 'Office network upgrade — 3 floors',
                'status'              => 'connected',
                'lost_reason'         => null,
                'expected_close_date' => '2026-05-01',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(8),
                'updated_at'          => $now->copy()->subDays(3),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Green Valley Foods',
                'contact_person'      => 'Suresh Patel',
                'phone'               => '+91 7654321098',
                'email'               => 'suresh@greenvalley.com',
                'website'             => null,
                'country'             => 'India',
                'city'                => 'Ahmedabad',
                'source'              => 'website',
                'product_interest'    => 'Herbal Extracts',
                'budget'              => 80000,
                'currency'            => 'INR',
                'notes'               => 'Regular monthly requirement for food processing',
                'status'              => 'closed_won',
                'lost_reason'         => null,
                'expected_close_date' => '2026-03-15',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(30),
                'updated_at'          => $now->copy()->subDays(5),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Saudi Gulf Tech',
                'contact_person'      => 'Abdullah Al Fahad',
                'phone'               => '+966 501234567',
                'email'               => 'abdullah@saudigulf.sa',
                'website'             => null,
                'country'             => 'Saudi Arabia',
                'city'                => 'Riyadh',
                'source'              => 'social_media',
                'product_interest'    => 'IT Infrastructure',
                'budget'              => 800000,
                'currency'            => 'SAR',
                'notes'               => 'Large enterprise deal — needs approval from board',
                'status'              => 'negotiation',
                'lost_reason'         => null,
                'expected_close_date' => '2026-05-30',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(12),
                'updated_at'          => $now->copy()->subDays(1),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Mumbai Pharma Ltd',
                'contact_person'      => 'Dr. Anil Mehta',
                'phone'               => '+91 9123456789',
                'email'               => 'anil@mumbaipharma.com',
                'website'             => null,
                'country'             => 'India',
                'city'                => 'Mumbai',
                'source'              => 'email',
                'product_interest'    => 'Plant Extracts — Pharmaceutical Grade',
                'budget'              => 300000,
                'currency'            => 'INR',
                'notes'               => 'GMP certified products chahiye. Long term vendor banane ki baat.',
                'status'              => 'requirement_discussion',
                'lost_reason'         => null,
                'expected_close_date' => '2026-06-01',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(5),
                'updated_at'          => $now->copy()->subDays(1),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Euro Naturals GmbH',
                'contact_person'      => 'Hans Mueller',
                'phone'               => '+49 1512345678',
                'email'               => 'hans@euronaturals.de',
                'website'             => 'https://euronaturals.de',
                'country'             => 'Germany',
                'city'                => 'Berlin',
                'source'              => 'whatsapp',
                'product_interest'    => 'Organic Extracts',
                'budget'              => 50000,
                'currency'            => 'EUR',
                'notes'               => 'EU organic certification required',
                'status'              => 'closed_lost',
                'lost_reason'         => 'Price too high compared to local supplier',
                'expected_close_date' => '2026-03-01',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(45),
                'updated_at'          => $now->copy()->subDays(10),
            ],
            [
                'user_id'             => $userId,
                'owner_id'            => $userId,
                'company_name'        => 'Jaipur IT Solutions',
                'contact_person'      => 'Vikram Singh',
                'phone'               => '+91 9001234567',
                'email'               => 'vikram@jaipurit.com',
                'website'             => null,
                'country'             => 'India',
                'city'                => 'Jaipur',
                'source'              => 'indiamart',
                'product_interest'    => 'Laptop & Desktop Bulk',
                'budget'              => 200000,
                'currency'            => 'INR',
                'notes'               => '20 laptops + 10 desktops for new office',
                'status'              => 'new',
                'lost_reason'         => null,
                'expected_close_date' => '2026-04-30',
                'po_id'               => null,
                'invoice_id'          => null,
                'client_id'           => null,
                'created_at'          => $now->copy()->subDays(2),
                'updated_at'          => $now->copy()->subDays(1),
            ],
        ];

        foreach ($leads as $lead) {
            DB::table('leads')->insert($lead);
        }

        $leadIds = DB::table('leads')->where('user_id', $userId)->pluck('id')->toArray();

        // ── 3. Campaign Leads ─────────────────────────────
        DB::table('campaign_leads')->insert([
            ['campaign_id' => $campaignIds[0], 'lead_id' => $leadIds[0], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => $campaignIds[0], 'lead_id' => $leadIds[2], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => $campaignIds[0], 'lead_id' => $leadIds[3], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => $campaignIds[1], 'lead_id' => $leadIds[1], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => $campaignIds[1], 'lead_id' => $leadIds[4], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => $campaignIds[2], 'lead_id' => $leadIds[0], 'assigned_to' => $userId, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── 4. Lead Activities — ek ek insert karo ────────
        $activities = [
            ['lead_id' => $leadIds[0], 'user_id' => $userId, 'type' => 'call',   'note' => 'Initial call — requirement discuss kiya. 2 PowerEdge servers chahiye.', 'call_duration' => 720,  'outcome' => 'Interested',       'created_at' => $now->copy()->subDays(15), 'updated_at' => $now->copy()->subDays(15)],
            ['lead_id' => $leadIds[0], 'user_id' => $userId, 'type' => 'email',  'note' => 'Product brochure aur pricing bheja.',                                    'call_duration' => null, 'outcome' => 'Sent',              'created_at' => $now->copy()->subDays(12), 'updated_at' => $now->copy()->subDays(12)],
            ['lead_id' => $leadIds[0], 'user_id' => $userId, 'type' => 'note',   'note' => 'Client ne quotation review ki — 1 week mein reply karenge.',             'call_duration' => null, 'outcome' => null,               'created_at' => $now->copy()->subDays(2),  'updated_at' => $now->copy()->subDays(2)],
            ['lead_id' => $leadIds[1], 'user_id' => $userId, 'type' => 'whatsapp','note' => 'WhatsApp pe initial contact — monthly requirement 500kg.',              'call_duration' => null, 'outcome' => 'Very Interested',   'created_at' => $now->copy()->subDays(20), 'updated_at' => $now->copy()->subDays(20)],
            ['lead_id' => $leadIds[1], 'user_id' => $userId, 'type' => 'call',   'note' => 'Video call — samples bhejne ke liye agree kiya.',                        'call_duration' => 1800, 'outcome' => 'Positive',          'created_at' => $now->copy()->subDays(14), 'updated_at' => $now->copy()->subDays(14)],
            ['lead_id' => $leadIds[1], 'user_id' => $userId, 'type' => 'email',  'note' => 'Sample shipment dispatch ki — tracking number share kiya.',              'call_duration' => null, 'outcome' => 'Sent',              'created_at' => $now->copy()->subDays(7),  'updated_at' => $now->copy()->subDays(7)],
            ['lead_id' => $leadIds[1], 'user_id' => $userId, 'type' => 'note',   'note' => 'Sample approved! Formal quotation maanga.',                              'call_duration' => null, 'outcome' => 'Ready to Order',    'created_at' => $now->copy()->subDays(1),  'updated_at' => $now->copy()->subDays(1)],
            ['lead_id' => $leadIds[2], 'user_id' => $userId, 'type' => 'call',   'note' => 'Cold call — receptionist se baat hui, decision maker nahi mile.',        'call_duration' => 180,  'outcome' => 'Callback needed',   'created_at' => $now->copy()->subDays(8),  'updated_at' => $now->copy()->subDays(8)],
            ['lead_id' => $leadIds[2], 'user_id' => $userId, 'type' => 'call',   'note' => 'Priya Sharma se directly baat hui — network upgrade plan share kiya.',   'call_duration' => 900,  'outcome' => 'Meeting scheduled', 'created_at' => $now->copy()->subDays(3),  'updated_at' => $now->copy()->subDays(3)],
            ['lead_id' => $leadIds[5], 'user_id' => $userId, 'type' => 'email',  'note' => 'Introduction email + product catalog bheja.',                            'call_duration' => null, 'outcome' => 'Sent',              'created_at' => $now->copy()->subDays(5),  'updated_at' => $now->copy()->subDays(5)],
            ['lead_id' => $leadIds[5], 'user_id' => $userId, 'type' => 'meeting','note' => 'Virtual meeting — GMP certification documents maange.',                  'call_duration' => 2700, 'outcome' => 'Documents needed',  'created_at' => $now->copy()->subDays(2),  'updated_at' => $now->copy()->subDays(2)],
            ['lead_id' => $leadIds[7], 'user_id' => $userId, 'type' => 'note',   'note' => 'IndiaMart enquiry received — new lead created.',                         'call_duration' => null, 'outcome' => null,               'created_at' => $now->copy()->subDays(2),  'updated_at' => $now->copy()->subDays(2)],
        ];

        foreach ($activities as $activity) {
            DB::table('lead_activities')->insert($activity);
        }

        // ── 5. Follow-ups ─────────────────────────────────
        $followUps = [
            ['lead_id' => $leadIds[0], 'user_id' => $userId, 'due_date' => $now->copy()->addDays(2),  'note' => 'Quotation follow-up call karna hai',  'is_done' => false, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['lead_id' => $leadIds[1], 'user_id' => $userId, 'due_date' => $now->copy()->addDays(1),  'note' => 'Formal quotation bhejni hai — urgent', 'is_done' => false, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['lead_id' => $leadIds[2], 'user_id' => $userId, 'due_date' => $now->copy()->subDays(3),  'note' => 'Decision maker se milna tha',          'is_done' => true,  'done_at' => $now->copy()->subDays(3), 'created_at' => $now->copy()->subDays(8), 'updated_at' => $now->copy()->subDays(3)],
            ['lead_id' => $leadIds[2], 'user_id' => $userId, 'due_date' => $now->copy()->addDays(5),  'note' => 'Site visit schedule karna',            'is_done' => false, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['lead_id' => $leadIds[4], 'user_id' => $userId, 'due_date' => $now->copy()->subDays(1),  'note' => 'Board approval ka follow-up',          'is_done' => false, 'done_at' => null, 'created_at' => $now->copy()->subDays(5), 'updated_at' => $now->copy()->subDays(5)],
            ['lead_id' => $leadIds[5], 'user_id' => $userId, 'due_date' => $now->copy()->addDays(3),  'note' => 'GMP docs bhejne hain',                 'is_done' => false, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['lead_id' => $leadIds[7], 'user_id' => $userId, 'due_date' => $now->copy()->addDays(1),  'note' => 'Initial call karna hai',               'is_done' => false, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($followUps as $followUp) {
            DB::table('lead_follow_ups')->insert($followUp);
        }

        $this->command->info('✅ CRM Seeder complete!');
        $this->command->info('   Campaigns:  ' . count($campaigns));
        $this->command->info('   Leads:      ' . count($leads));
        $this->command->info('   Activities: ' . count($activities));
        $this->command->info('   Follow-ups: ' . count($followUps));
    }
}