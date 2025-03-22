


## Setup

Same way you would install a typical laravel application.

    composer install

    npm install

    npm run dev

    copy .env.example to .env
    
    php artisan key:generate

    php arisan migrate

    php artisan db:seed

    php artisan serve

    php artisan queue:work

    Tests:
        php artisan test --filter BatchClaimServiceTest
        php artisan test --filter ClaimsTest
The UI is displayed on the root page

## Extra Notes
# **Batching Algorithm Documentation**

## **1. Overview**
The batching algorithm processes healthcare claims and assigns them to batches in a way that optimizes processing costs for insurers. The algorithm sorts claims based on the insurer’s date preference and cost, then attempts to assign each claim to a batch while ensuring that processing constraints are met.

The batching algorithm used is a combination of dynamic programming with a greedy approach. The claims are batched based on estimated processing cost, higher cost are batched first, since processing cost increases as the day goes by, this is the greedy approach, it is efficient but not very optimal, as more expensive claims may come in after batches for earlier days have been maxed out. This is where the dynamic approach comes in, after a new claim is submitted for a particular provider and insurer, all pending batches for the insurer and provider on the submited claim are re-processed and rebatched along with the new claim. This ensures that at all times the expensive claims are processed earlier than the less expensive ones. The dynamic approach trades a little performance for more cost optimization. However, the dynamic approach is implemented in such a way that performance cost is minimized, by using cursor for retrieving claims to be processed so that all records are not loaded into the memory at once, using database indexes, and for updating the batches, the update is done in a single query for all batched claims. Processing is also done in the background by a queued job.

In conclusion the algorithm is sort of an hybrid dynamic programming algorithm or an optimized greedy algorithm, creating a balance between performance and cost optimization.

---

## **2. Key Features**
- **Cost Optimization**: Claims with the highest processing cost are assigned to earlier batches in the month.
- **Dynamic Batching**: Claims are assigned to batches based on insurer constraints such as maximum batch size and daily processing limits.
- **Date Preference Handling**: Insurers can choose whether claims should be batched based on submission date or encounter date.
- **Lazy Loading for Efficiency**: The algorithm processes claims using a cursor to minimize memory usage.
- **Automatic Notification**: Once claims are batched, insurers receive an email notification.

---

## **3. Algorithm Steps**

### **Step 1: Initialize Batching Process**
- Retrieve insurer details using the claim’s `insurer_code`.
- Set batching parameters:
  - `date_preference`: Determines whether claims are sorted by submission or encounter date.
  - `min_batch_size` & `max_batch_size`: Defines batch size constraints.
  - `daily_processing_capacity`: Sets the maximum cost an insurer can process in a day.
- Fetch all unprocessed claims for the given insurer and provider.

### **Step 2: Fetch and Sort Claims**
- Retrieve all unprocessed claims from the database:
  - Filter by `insurer_code`, `provider_name`, and `is_processed = false`.
  - **Sort first by** `date_preference` (ascending).
  - **Then sort by** `base_processing_cost` (descending) to prioritize high-cost claims.

### **Step 3: Process Claims and Assign to Batches**
- Iterate through each claim and try to assign it to a batch.
- If the claim’s cost exceeds the daily processing capacity, **skip it**.
- Assign the claim to a batch on the closest available date while adhering to constraints:
  - If the batch for the date is full, try the next available date.

### **Step 4: Save Batches to Database**
- Store batch assignments in the database using an efficient bulk update.
- Each claim receives a `batch_id` and `batch_date`.

### **Step 5: Notify Insurer**
- Retrieve the insurer’s email from the database.
- If an email is available, send a notification with batch details.

---

## **4. Conclusion**
This batching algorithm efficiently groups healthcare claims to minimize processing costs while adhering to insurer constraints. It leverages sorting, lazy loading, and optimized database writes to ensure scalability and performance.




