<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Device;
use App\Services\IotConfiguration;
use App\Services\IotReadings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IotController extends Controller
{
    public function challenge()
    {
        $id = (string) Str::uuid();
        $proof = bin2hex(random_bytes(24));
        $exchange = bin2hex(random_bytes(32));
        DB::table('iot_pairings')->insert([
            'id' => $id, 'proof_hash' => hash('sha256', $proof), 'exchange_hash' => hash('sha256', $exchange),
            'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['pairing_id' => $id, 'operator_proof' => $proof, 'exchange_secret' => $exchange, 'expires_in' => 600], 201)->header('Cache-Control', 'no-store');
    }

    public function approve(Request $request, Device $device)
    {
        abort_unless($device->zone->greenhouse->company_id === auth('api')->user()->company_id, 403);
        $data = $request->validate(['pairing_id' => 'required|uuid', 'operator_proof' => 'required|string|max:100']);

        return DB::transaction(function () use ($device, $data) {
            $this->lockCompany($device);
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $this->available($device);
            abort_if($device->credential_hash !== null || $device->paired_at !== null, 409, 'Revoca la vinculación anterior primero.');
            $pairing = DB::table('iot_pairings')->where('id', $data['pairing_id'])->lockForUpdate()->first();
            abort_unless($pairing && ! $pairing->consumed_at && ! $pairing->device_id && now()->lt($pairing->expires_at)
                && hash_equals($pairing->proof_hash, hash('sha256', $data['operator_proof'])), 422, 'Vinculación inválida o vencida.');
            abort_if(DB::table('iot_pairings')->where('device_id', $device->id)->whereNull('consumed_at')->where('expires_at', '>', now())->exists(), 409);
            DB::table('iot_pairings')->where('id', $pairing->id)->update(['device_id' => $device->id, 'updated_at' => now()]);

            return response()->json(['message' => 'Autorizado. El dispositivo debe canjear su secreto local.']);
        });
    }

    public function exchange(Request $request)
    {
        $data = $request->validate(['pairing_id' => 'required|uuid', 'exchange_secret' => 'required|string|max:100']);
        // Lock order matches approval/revocation: device first, challenge second.
        $candidate = DB::table('iot_pairings')->where('id', $data['pairing_id'])->first();
        abort_unless($candidate && $candidate->device_id, 422);

        return DB::transaction(function () use ($candidate, $data) {
            $this->lockCompany(Device::findOrFail($candidate->device_id));
            $device = Device::whereKey($candidate->device_id)->lockForUpdate()->firstOrFail();
            $pairing = DB::table('iot_pairings')->where('id', $candidate->id)->lockForUpdate()->first();
            abort_unless($pairing && ! $pairing->consumed_at && now()->lt($pairing->expires_at)
                && hash_equals($pairing->exchange_hash, hash('sha256', $data['exchange_secret'])), 422);
            $this->available($device);
            abort_if($device->credential_hash !== null, 409);
            $credential = bin2hex(random_bytes(32));
            $device->forceFill(['credential_hash' => hash('sha256', $credential), 'paired_at' => now(), 'last_connection_at' => now()])->save();
            DB::table('iot_pairings')->where('id', $pairing->id)->update(['consumed_at' => now()]);

            return response()->json(['device_id' => $device->device_code, 'credential' => $credential])->header('Cache-Control', 'no-store');
        });
    }

    public function revoke(Device $device)
    {
        abort_unless($device->zone->greenhouse->company_id === auth('api')->user()->company_id, 403);
        DB::transaction(function () use ($device) {
            $this->lockCompany($device);
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $device->forceFill(['credential_hash' => null, 'paired_at' => null, 'last_connection_at' => null, 'last_reading_at' => null])->save();
            DB::table('iot_pairings')->where('device_id', $device->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        });

        return response()->json(['message' => 'Credencial revocada. Inicia otra vinculación para renovarla.']);
    }

    public function config(Request $request, IotConfiguration $configuration)
    {
        abort_unless($request->bearerToken(), 401);

        $candidate = Device::where('credential_hash', hash('sha256', $request->bearerToken()))->first();
        abort_unless($candidate, 401);

        return DB::transaction(function () use ($request, $configuration, $candidate) {
            $this->lockCompany($candidate);
            $device = Device::where('credential_hash', hash('sha256', $request->bearerToken()))->lockForUpdate()->first();
            abort_unless($device && $device->paired_at, 401);
            $this->available($device);
            try {
                $payload = $configuration->payload($device);
            } catch (ValidationException $e) {
                // Return normally so the diagnostic flag is committed.
                $device->forceFill(['configuration_error' => true, 'last_connection_at' => now()])->save();

                return response()->json(['message' => 'Configuración incompatible. Revisa los sensores registrados.'], 422);
            }
            $device->forceFill(['configuration_error' => false, 'last_connection_at' => now()])->save();

            return response()->json($payload)->header('Cache-Control', 'no-store');
        });
    }

    public function readings(Request $request, IotReadings $readings)
    {
        abort_unless($request->bearerToken(), 401);
        $hash = hash('sha256', $request->bearerToken());
        $candidate = Device::where('credential_hash', $hash)->first();
        abort_unless($candidate, 401);

        return DB::transaction(function () use ($candidate, $hash, $request, $readings) {
            $this->lockCompany($candidate);
            $device = Device::whereKey($candidate->id)->where('credential_hash', $hash)->lockForUpdate()->first();
            abort_unless($device && $device->paired_at, 401);
            $this->available($device);
            $data = $request->validate([
                'batch_id' => ['required', 'uuid'],
                'recorded_at' => ['sometimes', 'date_format:Y-m-d\\TH:i:s\\Z'],
                'readings' => ['required', 'array', 'min:1', 'max:32'],
                'readings.*' => ['required', 'array:sensor_id,measurement,unit,value,source'],
                'readings.*.sensor_id' => ['required', 'integer', 'min:1', 'distinct'],
                'readings.*.measurement' => ['required', 'in:temperature,ambient_humidity,soil_humidity'],
                'readings.*.unit' => ['required', 'in:°C,%,raw'],
                'readings.*.value' => ['required', 'numeric', 'between:-40,4095'],
            ]);
            $result = $readings->store($device, $data);

            return response()->json($result, $result['duplicate'] ? 200 : 201)->header('Cache-Control', 'no-store');
        }, 3);
    }

    private function lockCompany(Device $device): void
    {
        Company::whereKey($device->zone->greenhouse->company_id)->lockForUpdate()->firstOrFail();
    }

    private function available(Device $device): void
    {
        $company = $device->zone->greenhouse->company;
        abort_unless($company->status === 'active' && $company->effectivePlan(), 403);
        $company->syncSensorPlanRestrictions();
        $device->refresh()->load('zone.greenhouse');
        abort_unless($device->status === 'active' && $device->zone->status === 'active'
            && $device->zone->greenhouse->status === 'active'
            && ! $device->zone->plan_restricted && ! $device->zone->greenhouse->plan_restricted, 403);
        abort_unless($device->device_type === 'ESP32' && $device->board_model === 'esp32_devkit_v1' && $device->connection_type === 'wifi', 422);
    }
}
