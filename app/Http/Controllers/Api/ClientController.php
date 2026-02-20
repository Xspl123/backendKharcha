<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Repositories\Interfaces\ClientRepositoryInterface;

class ClientController extends Controller
{
    private $clientRepo;

    public function __construct(ClientRepositoryInterface $clientRepo)
    {
        $this->clientRepo = $clientRepo;
    }

    public function index()
    {
        $clients = $this->clientRepo->getAll();
        return ClientResource::collection($clients);
    }

    public function store(ClientRequest $request)
    {
        $client = $this->clientRepo->store($request->validated());

        return response()->json([
            'message' => 'Client created successfully',
            'client' => new ClientResource($client)
        ]);
    }

    public function show($id)
    {
        $client = $this->clientRepo->show($id);
        return new ClientResource($client);
    }

    public function update(ClientRequest $request, $id)
    {
        $client = $this->clientRepo->update($id, $request->validated());

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => new ClientResource($client)
        ]);
    }

    public function destroy($id)
    {
        $this->clientRepo->delete($id);

        return response()->json([
            'message' => 'Client deleted successfully'
        ]);
    }
}

