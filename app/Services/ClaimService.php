<?php
namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;


use App\Jobs\ProcessClaimBatch;
use App\Models\Claim;
use App\Models\Insurer;

class ClaimService
{
    public function createClaim(array $data)
    {
        $claimTotal = collect($data['items'])->sum(fn($item) => $item['unit_price'] * $item['quantity']);
        
        //validate that the sum of item amount is same as total claim amount
        if ($claimTotal != $data['total_amount']) {
            return response()->json([
                'status' => false,
                'message' => 'The sum of item amount is not equal to total claim amount'
            ], 422);
        }

        $claim = new Claim();

        $claim->insurer_code = $data['insurer_code'];
        $claim->provider_name = $data['provider_name'];
        $claim->encounter_date = $data['encounter_date'];
        $claim->specialty = $data['specialty'];
        $claim->priority_level = $data['priority_level'];
        $claim->total_amount = $data['total_amount'];
        $claim->items = json_encode($data['items']);

        if($claim->save()){
            //dispatch job to batch the claim
            ProcessClaimBatch::dispatch($claim);

            //send a response
            return response()->json([
                'status' => true,
                'message' => 'Claim submitted successfully',
                'data' => $claim
            ], 201);
        }

        return response()->json([
            'status' => false,
            'message' => 'Claim could not be submitted',
        ], 422);
        
    }


    //this is calculated differently because it is affected by the day of the month the claim is batched for
    public function calculateTotalProcessingCost($claim, $day_of_month)
    {
        //certain parameters are set in config for adjustability
        //these values may be set in database table, using config for simplicity
        $base_percentage = Config::get('claims.processingCost.base_percentage', 20); //use 20 as default
        $max_percentage = Config::get('claims.processingCost.max_percentage', 30); //use 20 as default

        $time_factor = round($base_percentage/100, 1) + (round($max_percentage/100, 1) * ($day_of_month - 1) / 29);
        $processing_cost = $this->calculateBaseProcessingCost($claim)*$time_factor;

        return $processing_cost;
    }

    //calculate processing cost based on non-variable parameters
    public function calculateBaseProcessingCost($claim)
    {   
        $insurer = Insurer::where('code', $claim->insurer_code)->first();
        $specialty_discount_percentage = Config::get('claims.processingCost.specialty_discount_percentage', 5); //use 5 as default
        $priority_factor = Config::get('claims.processingCost.priorityFactor', 0.02); //use 5 as default

        //factor in priority_level, the weight for priority levels could be set up
        //using the actual value for simplicity
        $processing_cost = $claim->total_amount * ($claim->priority_level * $priority_factor);
        
        //factor in specialty, if the claim is in the insurers specialty, it should lessen the cost
        if($claim->specialty === $insurer->specialty){
            $specialty_discount = ($specialty_discount_percentage/100) * $processing_cost;
            $processing_cost -= $specialty_discount;
        }

        $processing_cost = round($processing_cost,2);

        return $processing_cost;
    }
}