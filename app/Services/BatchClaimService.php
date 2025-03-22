<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Insurer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use App\Mail\ClaimBatchedMail;

class BatchClaimService
{
    protected array $batches = [];
    protected string $date_preference;
    protected int $min_batch_size;
    protected int $max_batch_size;
    protected float $daily_processing_capacity;
    protected ClaimService $claim_service;

    protected Claim $claim;

    public function __construct(ClaimService $claim_service)
    {
        $this->claim_service = $claim_service;
    }

    public function processBatch(Claim $claim): void
    {
        $this->claim = $claim;

        //retrieve the insurer and set parameters to be used to process batches for the given insurer
        $insurer = Insurer::where('code', $claim->insurer_code)->firstOrFail();

        if (!$insurer) {
            Log::error("Insurer with code {$claim->insurer_code} not found");
            return;
        }

        //use created_at as submission date if preference is submission date
        $this->date_preference = $insurer->claim_date_preference === 'submission_date' ? 'created_at' : 'encounter_date';
        $this->min_batch_size = $insurer->min_batch_size;
        $this->max_batch_size = $insurer->max_batch_size;
        $this->daily_processing_capacity = $insurer->daily_processing_capacity;
        
        //fetch all pending claims for the given insurer and provider so as to rebatch for cost optimization
        $claims = $this->getUnprocessedClaims($claim->insurer_code, $claim->provider_name);

        //process each claim and add to batches to be saved
        foreach ($claims as $claim) {
            $this->processSingleClaim($claim);
        }

        //update the processed claims with the batch id and date
        $this->saveBatches();
    }

    private function getUnprocessedClaims(string $insurer_code, string $provider_name)
    {
        //fetch all unprocessed claims for the given insurer and provider
        //sorting is done by the date preference of the insurer and base processing cost in ascending order
        //this is to ensure that the claims with the highest processing cost are processed first
        //and batched for earlier in the month to optimize cost
        return DB::table('claims')
            ->where([
                ['insurer_code', '=', $insurer_code],
                ['provider_name', '=', $provider_name],
                ['is_processed', '=', false]
            ])
            ->orderBy($this->date_preference, 'asc')
            ->orderBy('base_processing_cost', 'desc')
            ->cursor(); //use cursor for memory efficiency
    }

    private function processSingleClaim($claim): void
    {
        //if the cost of the claim is greater than the daily processing limit, then there's no need to batch
        //the claim
        if ($claim->base_processing_cost > $this->daily_processing_capacity) {
            return;
        }

        $batch_date = now();
        $additional_days = 0;

        //try to add the claim to a batch for the current date
        //if the claim cannot be added to the batch for the current date, then try to add to the next date
        while (!$this->addToBatch($claim, $batch_date)) {
            $batch_date->addDay();
            $additional_days++;

            //if the claim cannot be batched for the next 30 days, then stop trying to batch the claim
            //could be adjusted
            if ($additional_days > 30) {
                break;
            }
        }
    }

    private function addToBatch($claim, $batch_date): bool
    {
        //create batch id using the provider name and the batch date
        $batch_id = "{$claim->provider_name} " . $batch_date->format('M j Y');

        //check if the claim can be added to the batch for the given date
        if (!$this->canAddToBatch($claim, $batch_id, $batch_date)) {
            return false;
        }

        //calculate the total processing cost for the claim on specified date
        $day_of_month = $batch_date->format('d'); 
        $processing_cost = $this->claim_service->calculateTotalProcessingCost($claim, (int)$day_of_month);

        //create batch if it doesn't exist
        if (!isset($this->batches[$batch_id])) {
            $this->batches[$batch_id] = [
                'total_cost' => $processing_cost,
                'date' => $batch_date->format('Y-m-d'),
                'claims' => []
            ];
        } else {
            $this->batches[$batch_id]['total_cost'] += $processing_cost;
        }

        //add claim to batch
        $this->batches[$batch_id]['claims'][] = $claim->id;

        return true;
    }

    private function canAddToBatch($claim, string $batch_id, $batch_date): bool
    {
        //calculate the total processing cost for the claim on specified date
        $day_of_month = (int)$batch_date->format('d');
        $processing_cost = $this->claim_service->calculateTotalProcessingCost($claim, $day_of_month);

        //if processing cost is greater than the daily processing capacity, then the claim cannot be batched
        if ($claim->base_processing_cost > $this->daily_processing_capacity) {
            return false;
        }

        //if there is no batch for the specified date, then the claim can be batch for the date
        if (!isset($this->batches[$batch_id])) {
            return true;
        }


        $batch = $this->batches[$batch_id];
        $new_total_cost = $batch['total_cost'] + $processing_cost;
        $claim_count = count($batch['claims']);

        //if number of claims in batch is greate than max batch size, then the claim cannot be batched
        if ($claim_count >= $this->max_batch_size) {
            return false;
        }

        //if the total cost of the batch is greater than the daily processing capacity and the number of claims
        //in the batch is less than the min batch size, then the claim cannot be batched
        if ($new_total_cost >= $this->daily_processing_capacity && $claim_count < $this->min_batch_size) {
            return false;
        }

        //check if the total cost of the batch is less than the daily processing capacity
        return $new_total_cost <= $this->daily_processing_capacity;
    }

    private function saveBatches(): void
    {
        //batch_id and date for all claims are updated with a single query for efficiency
        foreach ($this->batches as $batch_id => $batch_data) {
            DB::table('claims')->whereIn('id', $batch_data['claims'])->update([
                'batch_id' => $batch_id,
                'batch_date' => $batch_data['date']
            ]);
        }

        //notify insurer of the batched claims
        $this->notifyInsurer();
    }

    private function notifyInsurer()
    {
        //get insurer email and send email notification
        $insurer = Insurer::where('code', $this->claim->insurer_code)->firstOrFail();

        if (!$insurer || !$insurer->email) {
            return;
        }

        Mail::to($insurer->email)->send(new ClaimBatchedMail($this->claim));
    }
}
