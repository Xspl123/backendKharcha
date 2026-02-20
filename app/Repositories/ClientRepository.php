<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\Interfaces\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    public function getAll()
    {
        return Client::where('user_id', auth()->id())->latest()->get();
    }

    public function store(array $data)
    {
        $data['user_id'] = auth()->id();
        return Client::create($data);
    }

    public function show($id)
    {
        return Client::where('user_id', auth()->id())
            ->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $client = $this->show($id);
        $client->update($data);

        return $client;
    }

    public function delete($id)
    {
        $client = $this->show($id);
        return $client->delete();
    }
}
