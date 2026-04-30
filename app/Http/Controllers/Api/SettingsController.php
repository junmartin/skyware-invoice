<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateSettingRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\AppSettingResource;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all settings
     * GET /api/settings
     */
    public function index()
    {
        $settings = AppSetting::all();

        return ApiResponse::success(
            AppSettingResource::collection($settings),
            'Settings retrieved'
        );
    }

    /**
     * Update settings
     * PUT /api/settings
     */
    public function update(UpdateSettingRequest $request)
    {
        $validated = $request->validated();

        try {
            // Update send_mode if provided
            if (isset($validated['send_mode'])) {
                AppSetting::updateOrCreate(
                    ['key' => 'send_mode'],
                    ['value' => $validated['send_mode']]
                );
            }

            // Update other settings if provided
            if (isset($validated['settings']) && is_array($validated['settings'])) {
                foreach ($validated['settings'] as $key => $value) {
                    AppSetting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }
            }

            $settings = AppSetting::all();

            return ApiResponse::success(
                AppSettingResource::collection($settings),
                'Settings updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update settings: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get specific setting value
     * GET /api/settings/{key}
     */
    public function show($key)
    {
        $setting = AppSetting::where('key', $key)->first();

        if (!$setting) {
            return ApiResponse::error('Setting not found', null, 404);
        }

        return ApiResponse::success(new AppSettingResource($setting), 'Setting retrieved');
    }
}
