<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\ClaimService;
use App\Services\BatchClaimService;

use App\Models\Claim;

class ProcessClaimBatch implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $claim;
    /**
     * Create a new job instance.
     */
    public function __construct(Claim $claim)
    {
        $this->claim = $claim;
    }

    /**
     * Execute the job.
     */
    public function handle(ClaimService $claimService): void
    {
        $processingCost = $claimService->calculateBaseProcessingCost($this->claim);

        // Update the cost in the claim record, to be used when sorting by cost
        $this->claim->base_processing_cost = $processingCost;
        $this->claim->save();

        //process batch
        (new BatchClaimService( $claimService))->processBatch($this->claim);
    }
}
