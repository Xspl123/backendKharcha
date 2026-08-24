<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['manage'],
            'companies' => ['view', 'create', 'edit', 'delete'],
            'clients' => ['view', 'create', 'edit', 'delete'],
            'invoices' => ['view', 'create', 'edit', 'delete'],
            'invoice_payments' => ['view', 'create', 'edit', 'delete'],
            'gst' => ['view', 'manage'],
            'vendors' => ['view', 'create', 'edit', 'delete'],
            'vendor_payments' => ['view', 'create', 'delete'],
            'purchase_orders' => ['view', 'create', 'edit', 'delete'],
            'sales_returns' => ['view', 'create', 'edit'],
            'purchase_returns' => ['view', 'create', 'edit'],
            'product_categories' => ['view', 'create', 'edit', 'delete'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'stock_movements' => ['view', 'create', 'delete'],
            'leads' => ['view', 'create', 'edit', 'delete'],
            'quotations' => ['view', 'create', 'edit', 'delete'],
            'campaigns' => ['view', 'create', 'edit', 'delete'],
            'exports' => ['view', 'create'],
        ];

        $permissionIds = [];

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module' => $module,
                        'label' => $this->makeLabel($module, $action),
                    ]
                );

                $permissionIds[] = $permission->id;
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            $superAdmin = Role::where('name', 'super_admin')->first();

            if ($superAdmin) {
                $superAdmin->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }

    private function makeLabel(string $module, string $action): string
    {
        $moduleLabel = str_replace('_', ' ', $module);

        return ucfirst($action) . ' ' . ucwords($moduleLabel);
    }
}
