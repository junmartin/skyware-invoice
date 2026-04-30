<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClientStoreRequest;
use App\Http\Requests\Api\ClientUpdateRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all clients with pagination and search
     * GET /api/clients
     * Query params: page, per_page, search
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Search by name or code
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->query('per_page', 20);
        $clients = $query->orderBy('name')->paginate($perPage);

        return ApiResponse::paginated($clients, 'Clients retrieved');
    }

    /**
     * Get single client by ID
     * GET /api/clients/{id}
     */
    public function show(Client $client)
    {
        $client->load('invoices');

        return ApiResponse::success(new ClientResource($client), 'Client retrieved');
    }

    /**
     * Create new client
     * POST /api/clients
     */
    public function store(ClientStoreRequest $request)
    {
        $client = Client::create(
            $request->validated() + ['is_active' => $request->boolean('is_active', true)]
        );

        return ApiResponse::success(
            new ClientResource($client),
            'Client created successfully',
            201
        );
    }

    /**
     * Update client
     * PUT /api/clients/{id}
     */
    public function update(ClientUpdateRequest $request, Client $client)
    {
        $data = $request->validated();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $client->update(array_filter($data, fn($value) => $value !== null));

        return ApiResponse::success(
            new ClientResource($client),
            'Client updated successfully'
        );
    }

    /**
     * Delete/Deactivate client
     * DELETE /api/clients/{id}
     */
    public function destroy(Client $client)
    {
        // Instead of hard delete, we deactivate the client
        $client->update(['is_active' => false]);

        return ApiResponse::success(null, 'Client deactivated successfully');
    }

    /**
     * Get client's invoices
     * GET /api/clients/{id}/invoices
     * Query params: page, per_page, status
     */
    public function invoices(Request $request, Client $client)
    {
        $query = $client->invoices();

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->query('per_page', 20);
        $invoices = $query->latest('issue_date')->paginate($perPage);

        return ApiResponse::paginated($invoices, 'Client invoices retrieved');
    }
}
