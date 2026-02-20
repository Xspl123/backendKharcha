<?php

namespace App\Repositories\Interfaces;

interface GstRepositoryInterface
{
    public function getSummary(string $period): array;
    public function getGstr1(string $period): array;
    public function getGstr3B(string $period): array;
    public function getHsnSummary(string $period): array;
    public function getReturns(array $filters): mixed;
    public function saveDraft(string $returnType, string $period): mixed;
    public function markFiled(int $id): mixed;
}