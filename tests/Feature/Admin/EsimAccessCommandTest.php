<?php

declare(strict_types=1);

use App\Enums\EsimProfileStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\EsimPlan;
use App\Models\EsimProfile;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

function esimAccessProfile(EsimProfileStatus $status): EsimProfile
{
    $provider = Provider::factory()->esimAccess()->create();

    foreach (['access_code' => 'test-access-code', 'secret_key' => 'test-secret-key'] as $key => $value) {
        $credential = new ProviderCredential;
        $credential->provider_id = $provider->id;
        $credential->environment = 'test';
        $credential->credential_key = $key;
        $credential->secret_reference = $value;
        $credential->save();
    }

    $plan = EsimPlan::factory()->create(['provider_id' => $provider->id]);
    $profile = new EsimProfile;
    $profile->user_id = User::factory()->create()->id;
    $profile->plan_id = $plan->id;
    $profile->provider_id = $provider->id;
    $profile->status = $status;
    $profile->external_profile_id = '23120118156818';
    $profile->save();

    return $profile;
}

it('cancels an unused profile through the eSIM Access cancel call', function (): void {
    Http::fake([
        'https://api.esimaccess.com/api/v1/open/esim/cancel' => Http::response([
            'success' => true,
            'errorCode' => '0',
            'obj' => [],
        ], 200),
    ]);

    $profile = esimAccessProfile(EsimProfileStatus::Ready);
    Sanctum::actingAs(staff(RoleName::Operator), ['admin']);

    $this->postJson('/api/v1/admin/esim/profiles/'.$profile->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($profile->fresh()->status)->toBe(EsimProfileStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'esim.profile.cancel')->where('entity_id', $profile->id)->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.esimaccess.com/api/v1/open/esim/cancel'
        && $request['esimTranNo'] === '23120118156818');
});

it('does not call eSIM Access when support lacks permission', function (): void {
    Http::fake();
    $profile = esimAccessProfile(EsimProfileStatus::Ready);
    Sanctum::actingAs(staff(RoleName::Support), ['admin']);

    $this->postJson('/api/v1/admin/esim/profiles/'.$profile->id.'/cancel')->assertForbidden();

    expect($profile->fresh()->status)->toBe(EsimProfileStatus::Ready);
    Http::assertNothingSent();
});

it('refuses a local status the provider command cannot reach', function (): void {
    Http::fake();
    $profile = esimAccessProfile(EsimProfileStatus::Expired);
    Sanctum::actingAs(staff(RoleName::Operator), ['admin']);

    $this->postJson('/api/v1/admin/esim/profiles/'.$profile->id.'/cancel')->assertStatus(409);

    Http::assertNothingSent();
});
