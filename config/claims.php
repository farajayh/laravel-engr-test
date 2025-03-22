<?php
//Set claims parameters

return [
    'processingCost' => [
        'base_percentage' => 20, //starting percentage for calculating processing cost
        'max_percentage' => 30, //maximum percentage for calculating processing cost
        'priority_factor' => 0.02, //factor to multiply priority level by to get total processing cost
        'specialty_discount_percentage' => 5, //the percentage of discount to processing cost if claim is in insurer's specialty
    ]
];    