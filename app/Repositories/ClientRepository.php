<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\Interfaces\ClientRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;

class ClientRepository implements ClientRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = [])
    {
        $query = $this->scopeQuery(Client::query())->latest();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('company_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }

        return $query->paginate($this->resolvePerPage($filters));
    }

    public function store(array $data)
    {
        $client = Client::create($this->scopeData($data));
        $this->bumpScopedCache(['clients', 'leads']);
        return $client;
    }

    public function show($id)
    {
        return $this->scopeQuery(Client::query())->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $client = $this->show($id);
        $client->update($data);
        $this->bumpScopedCache(['clients', 'leads']);
        return $client;
    }

    public function delete($id)
    {
        $deleted = $this->show($id)->delete();
        $this->bumpScopedCache(['clients', 'leads']);
        return $deleted;
    }
}
