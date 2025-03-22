<?php

namespace Tests\Feature;

use App\Models\Insurer;
use App\Services\ClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClaimsTest extends TestCase
{
    use RefreshDatabase;

    protected $claimService;

    public function setUp(): void
    {
        parent::setUp();

        //mock ClaimService
        $this->claimService = \Mockery::mock(ClaimService::class);
        $this->app->instance(ClaimService::class, $this->claimService);
    }

    
    public function test_it_creates_a_claim_successfully()
    {
        //create an insurer first
        $insurer = Insurer::factory()->create([
            'name'=>'Insurer A', 
            'code'=> 'INS-A',
            'claim_date_preference' => 'submission_date',
            'daily_processing_capacity' => 100000,
            'min_batch_size' => 1, 
            'max_batch_size' => 20,
            'specialty' => 'Cardiology'
        ]);

        $payload = [
            'insurer_code'   => 'INS-A',
            'provider_name'  => 'Provider A',
            'encounter_date' => now()->toDateString(),
            'specialty'      => 'Cardiology',
            'priority_level' => 3,
            'total_amount'   => 5000,
            'items' => [
                ['name' => 'Item one', 'unit_price' => 2000, 'quantity' => 1],
                ['name' => 'Item two', 'unit_price' => 3000, 'quantity' => 1],
            ]
        ];

        //mock the createClaim method        
        $this->claimService->shouldReceive('createClaim')->once()->andReturn(response()->json([
            'status'  => true,
            'message' => 'Claim submitted successfully',
        ], 201));

        
        $response = $this->postJson('/api/claim', $payload);
        
        $response->assertStatus(201)
                 ->assertJson([
                     'status'  => true,
                     'message' => 'Claim submitted successfully',
                 ]);
    }

   
    public function test_it_fails_validation_when_required_fields_are_missing()
    {
        $payload = [
            'provider_name' => 'Provider A',
        ];

        $response = $this->postJson('/api/claim', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => false,
                    'message' => 'Invalid input',
                    'error' => [
                        'insurer_code' => ['The insurer code field is required.'],
                        'encounter_date' => ['The encounter date field is required.'],
                        'specialty' => ['The specialty field is required.'],
                        'priority_level' => ['The priority level field is required.'],
                        'total_amount' => ['The total amount field is required.'],
                        'items' => ['The items field is required.'],
                    ],
                ]);
    }

    public function test_it_fails_validation_when_specialty_is_invalid()
    {
        $payload = [
            'insurer_code'   => 'INS123',
            'provider_name'  => 'ABC Clinic',
            'encounter_date' => now()->toDateString(),
            'specialty'      => 'InvalidSpecialty', // Invalid value
            'priority_level' => 3,
            'total_amount'   => 2000,
            'items' => [
                ['name' => 'Item one', 'unit_price' => 2000, 'quantity' => 1],
            ]
        ];

        
        $response = $this->postJson('/api/claim', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => false,
                    'message' => 'Invalid input',
                    'error' => [
                        'specialty' => ['The selected specialty is invalid.']
                    ],
                ]);
    }
}
