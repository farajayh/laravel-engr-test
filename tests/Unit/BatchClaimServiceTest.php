<?php

namespace Tests\Unit;

use App\Mail\ClaimBatchedMail;
use App\Models\Claim;
use App\Models\Insurer;
use App\Services\BatchClaimService;
use App\Services\ClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;


class BatchClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $batchClaimService;
    protected $claimServiceMock;

    public function setUp(): void
    {
        parent::setUp();

        //create an insurer
        $insurer = Insurer::factory()->create([
            'code' => 'TEST-123',
            'claim_date_preference' => 'submission_date',
            'min_batch_size' => 2,
            'max_batch_size' => 5,
            'specialty' => 'Cardiology',
            'daily_processing_capacity' => 500,
        ]);

        //create a mock ClaimService
        $this->claimServiceMock = Mockery::mock(ClaimService::class);
        $this->claimServiceMock->shouldReceive('calculateTotalProcessingCost')
            ->andReturnUsing(function ($claim, $day) {
                return $claim->base_processing_cost;
            });


        //initialize service
        $this->batchClaimService = new BatchClaimService($this->claimServiceMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    
    public function test_it_batches_claims_correctly()
    {
        $claims = Claim::factory()->count(3)->create([
            'insurer_code' => 'TEST-123', 
            'provider_name'  => 'Test Provider',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty'      => 'Cardiology',
            'priority_level' => 4,
            'total_amount'   => 200,
            'items' => json_encode([
                ['name' => 'item one', 'unit_price' => 100, 'quantity' => 1],
                ['name' => 'item two', 'unit_price' => 100, 'quantity' => 1],
            ])
        ]);

        //process the first claim should batch all 3 claims
        $this->batchClaimService->processBatch($claims->first());

        $batch = DB::table('claims')->get();

        $this->assertCount(3, $batch, "All claims should be batched");
    }

    public function test_it_skips_claims_exceeding_processing_capacity()
    {
        $items =  [
            ['name' => 'item one', 'unit_price' => 2000, 'quantity' => 3],
            ['name' => 'item two', 'unit_price' => 2000, 'quantity' => 2],
        ];
        $claim = Claim::factory()->create([
            'insurer_code'   => 'TEST-123',
            'provider_name'  => 'Test ProviderB',
            'base_processing_cost' => 1500,
            'encounter_date' => now()->format('Y-m-d'),
            'specialty'      => 'Cardiology',
            'priority_level' => 4,
            'total_amount'   => 10000,
            'items' => json_encode($items)
        ]);

        //claim should not be batched due to exceeding processing capacity
        $this->batchClaimService->processBatch($claim);

        $batch = DB::table('claims')->Where('provider_name', 'Test ProviderB')->whereNotNull('batch_id')->get();
        
        $this->assertCount(0, $batch, "Claim exceeding capacity should not be batched");
    }

    public function test_it_assigns_claims_to_existing_batch_if_possible()
    {
        $claim1 = Claim::factory()->create([
            'insurer_code'   => 'TEST-123',
            'provider_name'  => 'Test Provider',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty'      => 'Cardiology',
            'priority_level' => 4,
            'total_amount'   => 300,
            'items' => json_encode([
                ['name' => 'item one', 'unit_price' => 150, 'quantity' => 1],
                ['name' => 'item two', 'unit_price' => 150, 'quantity' => 1],
            ])
        ]);


        $claim2 = Claim::factory()->create([
            'insurer_code'   => 'TEST-123',
            'provider_name'  => 'Test Provider',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty'      => 'Cardiology',
            'priority_level' => 4,
            'total_amount'   => 500,
            'items' => json_encode([
                ['name' => 'item one', 'unit_price' => 200, 'quantity' => 1],
                ['name' => 'item two', 'unit_price' => 300, 'quantity' => 1],
            ])
        ]);

        //processing batch on both claims should assign them to same batch
        $this->batchClaimService->processBatch($claim1);
        $this->batchClaimService->processBatch($claim2);

        //get the count of created batches
        $batchCounts = DB::table('claims')
            ->select('batch_id', DB::raw('count(*) as count'))
            ->groupBy('batch_id')
            ->get();

        $this->assertEquals(1, $batchCounts->count(), "Claims should be grouped into the same batch");
    }


    public function test_it_creates_a_new_batch_if_existing_batches_are_full()
    {
        //create multiple claims
        for ($i = 0; $i < 6; $i++) {
            Claim::factory()->create([
                'insurer_code'   => 'TEST-123',
                'provider_name'  => 'Test Provider',
                'encounter_date' => now()->format('Y-m-d'),
                'specialty'      => 'Cardiology',
                'priority_level' => 4,
                'total_amount'   => 500,
                'items' => json_encode([
                    ['name' => 'item one', 'unit_price' => 100, 'quantity' => 1],
                    ['name' => 'item two', 'unit_price' => 100, 'quantity' => 1],
                ])
            ]);
        }

        //processing the first claim should process batching for pending claims with the same provider and insurer
        $this->batchClaimService->processBatch(Claim::first());

        //get the number of batches created
        $batchCounts = DB::table('claims')
            ->select('batch_id', DB::raw('count(*) as count'))
            ->groupBy('batch_id')
            ->get();

        $this->assertGreaterThan(1, $batchCounts->count(), "More than one batch should be created due to max batch size limit");
    }


    public function test_it_includes_claim_details_in_email()
    {
        //ensure database is fresh for testing
        $this->refreshDatabase();

        //create an insurer
        $insurer = Insurer::factory()->create([
            'code' => 'INS123',
            'email' => 'insurer@example.com',
        ]);

        //create a claim associated with the insurer
        $claim = Claim::factory()->create([
            'insurer_code' => $insurer->code, //ensure it matches an existing insurer
            'batch_id' => 'BATCH123',
            'batch_date' => now()->format('Y-m-d'),
            'base_processing_cost' => 100,
            'provider_name'  => 'Test Provider',
            'encounter_date' => now()->format('Y-m-d'),
            'specialty'      => 'Cardiology',
            'priority_level' => 4,
            'total_amount'   => 500,
            'items' => json_encode([
                ['name' => 'item one', 'unit_price' => 100, 'quantity' => 1],
                ['name' => 'item two', 'unit_price' => 100, 'quantity' => 1],
            ])
    ]);

        //mailable instance
        $mailable = new ClaimBatchedMail($claim);

        //assert email contains correct claim details
        $mailable->assertSeeInHtml('Claim ID: ' . $claim->id);
        $mailable->assertSeeInHtml('Batch ID: ' . $claim->batch_id);
        $mailable->assertSeeInHtml('Batch Date: ' . $claim->batch_date);
        $mailable->assertSeeInHtml('Provider: ' . $claim->provider_name);
        $mailable->assertSeeInHtml('Insurer: ' . $claim->insure_code);
    }
}