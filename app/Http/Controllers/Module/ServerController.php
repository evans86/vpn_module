<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Location\Location;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ServerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            Log::info('Accessing servers list', [
                'source' => 'server',
                'user_id' => auth()->id()
            ]);

            $servers = Server::with('location')->paginate(10);
            $locations = Location::pluck('code', 'id')->mapWithKeys(function ($code, $id) {
                return [$id => $code . ' ' . Location::find($id)->emoji];
            });

            return view('module.server.index', compact('servers', 'locations'));
        } catch (\Exception $e) {
            Log::error('Error accessing servers list', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return back()->with('error', 'Error loading servers list: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Creating new server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'location_id' => $request->input('location_id'),
                'provider' => $request->input('provider')
            ]);

            // Валидация входных данных
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:location,id',
                'provider' => 'required|string|in:' . Server::VDSINA
            ]);

            $strategy = new ServerStrategy($validated['provider']);
            $server = $strategy->configure($validated['location_id'], $validated['provider'], false);

            Log::info('Server created successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'location_id' => $server->location_id,
                'provider' => $server->provider
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Server created successfully',
                'data' => $server
            ]);

        } catch (ValidationException $e) {
            Log::warning('Server creation validation failed', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\RuntimeException $e) {
            Log::error('Server creation failed', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Server limit reached')) {
                return response()->json([
                    'success' => false,
                    'message' => 'VDSina server limit reached (limit: 5 servers). ' .
                                'Please delete unused servers or add funds.',
                    'error_code' => 'SERVER_LIMIT_REACHED'
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create server: ' . $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Unexpected error during server creation', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the server'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server)
    {
        try {
            Log::info('Updating server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $request->except(['_token', '_method'])
            ]);

            $server->update($request->all());

            Log::info('Server updated successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return redirect()->route('module.server.index')
                ->with('success', 'Server updated successfully');
        } catch (\Exception $e) {
            Log::error('Error updating server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $request->all()
            ]);

            return redirect()->route('module.server.index')
                ->with('error', 'Error updating server: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function destroy(Server $server): JsonResponse
    {
        try {
            Log::info('Deleting server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'provider' => $server->provider
            ]);

            // Создаем стратегию для провайдера и удаляем сервер
            $strategy = new ServerStrategy($server->provider);
            $strategy->delete($server);

            Log::info('Server deleted successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return response()->json([
                'message' => 'Server deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return response()->json([
                'message' => 'Error deleting server: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get server status
     */
    public function getStatus(Server $server): JsonResponse
    {
        return response()->json([
            'status' => $server->server_status,
            'message' => $server->status_label
        ]);
    }
}
