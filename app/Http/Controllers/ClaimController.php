<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ClaimService;

class ClaimController extends Controller
{
    protected $claimService;

    public function __construct(ClaimService $claimService)
    {
        $this->claimService = $claimService;
    }

    public function store(Request $request)
    {
        //do validation
        $validation = Validator::make($request->all(),[
                'insurer_code' => 'required|string',
                'provider_name' => 'required|string',
                'encounter_date' => 'required|date',
                'specialty' => 'required|string|in:Cardiology,Orthopedics,Dermatology,Pediatrics,Neurology',
                'priority_level' => 'required|integer|min:1|max:5',
                'total_amount' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.name' => 'required|string',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.quantity' => 'required|integer|min:1'
        ]);

        if($validation->fails()){
            return response()->json([
                'status'    => false,
                'message'   => "Invalid input",
                'error'     => $validation->errors()
            ], 422);
        }

        $validated  = $validation->validated();
        return $this->claimService->createClaim($validated);
    }
}
