<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolePresetSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permissions')) {
            return;
        }

        $presets = [
            'super_admin' => [
                'label' => 'Super Admin',
                'description' => 'Full system access',
                'color' => '#dc2626',
                'permissions' => ['*'],
            ],
            'org_admin' => [
                'label' => 'Org Admin',
                'description' => 'Organisation-wide operational access',
                'color' => '#2563eb',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit',
                    'roles.manage',
                    'companies.view', 'companies.create', 'companies.edit',
                    'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
                    'invoice_payments.view', 'invoice_payments.create', 'invoice_payments.edit', 'invoice_payments.delete',
                    'gst.view', 'gst.manage',
                    'vendors.view', 'vendors.create', 'vendors.edit', 'vendors.delete',
                    'vendor_payments.view', 'vendor_payments.create', 'vendor_payments.delete',
                    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.delete',
                    'sales_returns.view', 'sales_returns.create', 'sales_returns.edit',
                    'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit',
                    'product_categories.view', 'product_categories.create', 'product_categories.edit', 'product_categories.delete',
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'stock_movements.view', 'stock_movements.create', 'stock_movements.delete',
                    'leads.view', 'leads.create', 'leads.edit', 'leads.delete',
                    'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.delete',
                    'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
                    'exports.view', 'exports.create',
                ],
            ],
            'sales_manager' => [
                'label' => 'Sales Manager',
                'description' => 'Sales pipeline, client, invoice and campaign access',
                'color' => '#7c3aed',
                'permissions' => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'invoice_payments.view',
                    'products.view',
                    'product_categories.view',
                    'stock_movements.view',
                    'leads.view', 'leads.create', 'leads.edit',
                    'quotations.view', 'quotations.create', 'quotations.edit',
                    'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
                    'exports.view', 'exports.create',
                    'companies.view',
                ],
            ],
            'sales_agent' => [
                'label' => 'Sales Agent',
                'description' => 'Lead and client follow-up access',
                'color' => '#0f766e',
                'permissions' => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'invoices.view',
                    'products.view',
                    'product_categories.view',
                    'leads.view', 'leads.create', 'leads.edit',
                    'quotations.view',
                    'campaigns.view',
                    'exports.view',
                    'companies.view',
                ],
            ],
            'finance' => [
                'label' => 'Finance',
                'description' => 'Invoice, payments and GST access',
                'color' => '#ca8a04',
                'permissions' => [
                    'clients.view',
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'invoice_payments.view', 'invoice_payments.create', 'invoice_payments.edit', 'invoice_payments.delete',
                    'quotations.view',
                    'gst.view', 'gst.manage',
                    'vendors.view',
                    'vendor_payments.view', 'vendor_payments.create', 'vendor_payments.delete',
                    'purchase_orders.view',
                    'sales_returns.view',
                    'purchase_returns.view',
                    'exports.view', 'exports.create',
                    'companies.view',
                ],
            ],
            'operations' => [
                'label' => 'Operations',
                'description' => 'Vendor, purchase and inventory access',
                'color' => '#ea580c',
                'permissions' => [
                    'vendors.view', 'vendors.create', 'vendors.edit',
                    'vendor_payments.view',
                    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
                    'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.edit',
                    'product_categories.view', 'product_categories.create', 'product_categories.edit',
                    'products.view', 'products.create', 'products.edit',
                    'quotations.view',
                    'stock_movements.view', 'stock_movements.create', 'stock_movements.delete',
                    'exports.view', 'exports.create',
                    'companies.view',
                ],
            ],
        ];

        $allPermissionIds = Permission::pluck('id', 'name');

        foreach ($presets as $name => $preset) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'label' => $preset['label'],
                    'description' => $preset['description'],
                    'color' => $preset['color'],
                ]
            );

            $permissionIds = $preset['permissions'] === ['*']
                ? $allPermissionIds->values()->all()
                : $allPermissionIds->only($preset['permissions'])->values()->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
