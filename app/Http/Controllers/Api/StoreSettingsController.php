<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateStoreSettingsRequest;
use App\Services\StoreSettings\StoreSettingsService;
use Illuminate\Http\JsonResponse;

class StoreSettingsController extends Controller
{
    public function __construct(protected StoreSettingsService $settingsService) {}

    /**
     * Get store settings.
     *
     * Retrieves the settings for the authenticated customer store.
     *
     * @group Customer Management
     *
     * @subgroup Store Settings
     *
     * @authenticated
     *
     * @response 200 scenario=Success {
     *     "code": 200,
     *     "data": {
     *         "settings": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "domain": null,
     *             "slug": "my-store",
     *             "product_layout": "default",
     *             "brand_color": "#111111",
     *             "background_color": "#FFFFFF",
     *             "created_at": "2026-07-21 12:00:00",
     *             "updated_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 404 scenario=NotFound {
     *     "code": 404,
     *     "message": "Settings not found."
     * }
     */
    public function show(): JsonResponse
    {
        return $this->settingsService->getSettings()->toJson();
    }

    /**
     * Update store settings.
     *
     * Updates the settings for the authenticated customer store.
     *
     * @group Customer Management
     *
     * @subgroup Store Settings
     *
     * @authenticated
     *
     * @bodyParam domain string The custom domain. Example: mystore.com
     * @bodyParam product_layout string The product layout style. Example: grid
     * @bodyParam brand_color string The brand color hex. Example: #222222
     * @bodyParam background_color string The background color hex. Example: #F5F5F5
     *
     * @response 200 scenario=Updated {
     *     "code": 200,
     *     "message": "Settings updated successfully.",
     *     "data": {
     *         "settings": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "store_id": "01953801-abcd-1234-5678-1234567890ab",
     *             "domain": "mystore.com",
     *             "slug": "my-store",
     *             "product_layout": "grid",
     *             "brand_color": "#222222",
     *             "background_color": "#F5F5F5",
     *             "created_at": "2026-07-21 12:00:00",
     *             "updated_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 404 scenario=NotFound {
     *     "code": 404,
     *     "message": "Settings not found."
     * }
     * @response 422 scenario=ValidationError {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "domain": ["The domain field must be a string."],
     *         "product_layout": ["The product layout field must be a string."]
     *     }
     * }
     * @response 500 scenario=ServerError {
     *     "code": 500,
     *     "message": "Failed to update settings.",
     *     "error": "..."
     * }
     */
    public function update(UpdateStoreSettingsRequest $request): JsonResponse
    {
        return $this->settingsService->updateSettings($request->validated())->toJson();
    }
}
