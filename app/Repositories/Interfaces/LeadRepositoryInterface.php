<?php

namespace App\Repositories\Interfaces;

use App\Models\Lead;

interface LeadRepositoryInterface
{
    public function getAll(array $filters = []);
    public function findById(int $id): Lead;
    public function create(array $data): Lead;
    public function update(int $id, array $data): Lead;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status, ?string $lostReason = null): Lead;
    public function addActivity(int $leadId, array $data);
    public function addFollowUp(int $leadId, array $data);
    public function markFollowUpDone(int $followUpId);
    public function getLeadProducts(int $leadId);
    public function addLeadProduct(int $leadId, array $data);
    public function updateLeadProduct(int $leadId, int $leadProductId, array $data);
    public function deleteLeadProduct(int $leadId, int $leadProductId): bool;
    public function linkPurchaseOrder(int $leadId, int $purchaseOrderId): Lead;
    public function linkInvoice(int $leadId, int $invoiceId): Lead;
    public function getSummary(): array;
    public function getPipelineStats(): array;
    public function getDueFollowUps(): array;
}