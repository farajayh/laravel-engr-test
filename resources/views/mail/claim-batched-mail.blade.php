<x-mail::message>
# Claim Batched Notification

Dear Insurer,

A new claim has been processed.

### **Claim Details:**
- Claim ID: {{ $claim->id }}
- Batch ID: {{ $claim->batch_id }}
- Batch Date: {{ $claim->batch_date }}
- Provider: {{ $claim->provider_name }}
- Insurer: {{ $claim->insurer_code }}


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
