<?php

namespace Tests\Feature;

use App\Http\Controllers\PointOfSaleController;
use App\Services\CustomerAccessToken;
use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CustomerOrderTokenTest extends TestCase
{
    public function test_only_the_authenticated_customer_can_reach_checkout(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'jwt.secret' => base64_encode(random_bytes(32)),
        ]);

        Schema::create('api_credentials', function (Blueprint $table) {
            $table->string('client_id');
            $table->string('client_api');
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('cust_email');
            $table->string('password');
            $table->boolean('cust_delete')->default(false);
        });
        DB::table('api_credentials')->insert(['client_id' => 'test-app', 'client_api' => 'test-key']);
        DB::table('customers')->insert([
            'id' => 18,
            'cust_email' => 'customer@example.test',
            'password' => Hash::make('fixture-password'),
        ]);

        $login = [
            'client_id' => 'test-app',
            'api_key' => 'test-key',
            'l_username_email' => 'customer@example.test',
            'l_password' => 'fixture-password',
        ];
        $failedLogin = $this->postJson('/api/login', array_replace($login, ['l_password' => 'incorrect']));
        $this->assertArrayNotHasKey('access_token', $failedLogin->json());
        $token = $this->postJson('/api/login', $login)
            ->assertOk()
            ->assertJsonPath('cust_id', 18)
            ->assertJsonPath('token_type', 'Bearer')
            ->json('access_token');
        $this->assertIsString($token);

        $controller = Mockery::mock(PointOfSaleController::class)->makePartial();
        $controller->shouldReceive('saveTransaction')->twice()
            ->with(Mockery::on(fn (Request $request) => $request->attributes->get('authenticated_customer_id') === 18))
            ->andReturn(response()->json(['checkout_reached' => true]));
        $this->app->instance(PointOfSaleController::class, $controller);

        [$header, , $signature] = explode('.', $token);
        $modifiedPayload = JWT::urlsafeB64Encode(json_encode([
            'user_id' => 19,
            'iss' => config('app.url'),
            'aud' => 'topscore-orders',
            'exp' => time() + CustomerAccessToken::LIFETIME,
        ]));
        $modifiedToken = "$header.$modifiedPayload.$signature";

        foreach (['/api/save_pos', '/api/order'] as $route) {
            $this->postJson($route, ['cust_id' => 18])->assertUnauthorized();
            $this->postJson($route, ['cust_id' => 19], ['Authorization' => "Bearer $modifiedToken"])
                ->assertUnauthorized();
            $this->postJson($route, ['cust_id' => 19], ['Authorization' => "Bearer $token"])
                ->assertForbidden();
            $this->postJson($route, ['cust_id' => 18], ['Authorization' => "Bearer $token"])
                ->assertOk()->assertJsonPath('checkout_reached', true);
        }

        $previousTimestamp = JWT::$timestamp;
        try {
            JWT::$timestamp = time() + CustomerAccessToken::LIFETIME;
            $this->postJson('/api/order', ['cust_id' => 18], ['Authorization' => "Bearer $token"])
                ->assertUnauthorized();
        } finally {
            JWT::$timestamp = $previousTimestamp;
        }
    }
}
