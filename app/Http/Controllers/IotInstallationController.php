<?php

namespace App\Http\Controllers;

use App\Services\IotFirmwareCatalog;
use App\Models\Device;
use Illuminate\Http\Request;

class IotInstallationController extends Controller
{
    public function index(Request $request, IotFirmwareCatalog $catalog)
    {
        $devices = Device::query()
            ->whereHas('zone.greenhouse', fn ($query) => $query->where('company_id', $request->user()->company_id))
            ->where('device_type', 'ESP32')->where('board_model', 'esp32_devkit_v1')
            ->where('connection_type', 'wifi')->where('status', 'active')
            ->whereNull('paired_at')->whereNull('credential_hash')
            ->orderBy('name')->get(['id', 'name', 'device_code']);

        return response()->view('iot.installation', ['releases' => $catalog->listing($request->user()->company_id), 'devices' => $devices])
            ->header('Cache-Control', 'private, no-store')
            ->header('Permissions-Policy', 'serial=(self)');
    }

    public function download(Request $request, string $release, string $format, IotFirmwareCatalog $catalog)
    {
        $path = $catalog->artifact($release, $format, $request->user()->company_id);
        abort_unless($path, 404);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ])->setPrivate();
    }
}
