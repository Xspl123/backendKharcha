<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('org_categories')) {
            Schema::create('org_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('org_id')->nullable();
                $table->string('name', 100);
                $table->string('slug', 100)->nullable();
                $table->enum('type', ['income', 'expense']);
                $table->string('color', 50)->default('#64748b');
                $table->string('icon', 50)->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['type', 'is_active']);
                $table->index('slug');
            });

            // Seed initial standard categories
            $defaults = [
                // Incomes (Money In)
                ['name' => 'Client Payment (Invoice)',       'slug' => 'client_payment',   'type' => 'income',  'color' => '#10b981', 'icon' => '💵', 'is_system' => true],
                ['name' => 'Purchase Return Refund',         'slug' => 'purchase_return',  'type' => 'income',  'color' => '#10b981', 'icon' => '↩️', 'is_system' => true],
                ['name' => 'Owner Capital Infusion',         'slug' => 'owner_capital',    'type' => 'income',  'color' => '#0ea5e9', 'icon' => '💼', 'is_system' => true],
                ['name' => 'Bank / FD Interest',             'slug' => 'interest_income',  'type' => 'income',  'color' => '#10b981', 'icon' => '🏦', 'is_system' => true],
                ['name' => 'Other Direct Income',            'slug' => 'other_income',     'type' => 'income',  'color' => '#10b981', 'icon' => '➕', 'is_system' => true],

                // Expenses (Money Out)
                ['name' => 'Vendor Payout (PO)',             'slug' => 'vendor_payment',   'type' => 'expense', 'color' => '#ef4444', 'icon' => '🧾', 'is_system' => true],
                ['name' => 'Sales Return Refund',            'slug' => 'sales_return',     'type' => 'expense', 'color' => '#ef4444', 'icon' => '↩️', 'is_system' => true],
                ['name' => 'Office / Shop Rent',             'slug' => 'office_rent',      'type' => 'expense', 'color' => '#f59e0b', 'icon' => '🏢', 'is_system' => true],
                ['name' => 'Staff Salary & Wages',           'slug' => 'salary',           'type' => 'expense', 'color' => '#6366f1', 'icon' => '👥', 'is_system' => true],
                ['name' => 'Electricity & Utilities',       'slug' => 'electricity_bill', 'type' => 'expense', 'color' => '#f59e0b', 'icon' => '⚡', 'is_system' => true],
                ['name' => 'Tea, Pantry & Refreshments',     'slug' => 'tea_snacks',       'type' => 'expense', 'color' => '#f59e0b', 'icon' => '☕', 'is_system' => true],
                ['name' => 'Office Supplies & Stationery',   'slug' => 'office_expense',   'type' => 'expense', 'color' => '#f59e0b', 'icon' => '📦', 'is_system' => true],
                ['name' => 'Freight & Transportation',       'slug' => 'freight_logistics','type' => 'expense', 'color' => '#f59e0b', 'icon' => '🚚', 'is_system' => true],
                ['name' => 'Marketing & Advertising',        'slug' => 'marketing_ads',    'type' => 'expense', 'color' => '#f59e0b', 'icon' => '📢', 'is_system' => true],
                ['name' => 'Repairs & Maintenance',          'slug' => 'repairs',          'type' => 'expense', 'color' => '#f59e0b', 'icon' => '🛠️', 'is_system' => true],
                ['name' => 'Other Business Expense',         'slug' => 'other_expense',    'type' => 'expense', 'color' => '#ef4444', 'icon' => '📌', 'is_system' => true],
            ];

            foreach ($defaults as $i => $cat) {
                DB::table('org_categories')->insert([
                    'name'       => $cat['name'],
                    'slug'       => $cat['slug'],
                    'type'       => $cat['type'],
                    'color'      => $cat['color'],
                    'icon'       => $cat['icon'],
                    'is_system'  => $cat['is_system'],
                    'is_active'  => true,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_categories');
    }
};