<template>
  <GuestLayout>

    <Head title="Submit Claim" />

    <div class="text-center font-bold text-xl mb-6">Submit A Claim</div>

    <div>
      <form @submit.prevent="submitClaim">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-medium">Insurer Code</label>
            <input v-model="claim.insurer_code" type="text" class="w-full border rounded p-2" required />
          </div>

          <div>
            <label class="block font-medium">Provider Name</label>
            <input v-model="claim.provider_name" type="text" class="w-full border rounded p-2" required />
          </div>

          <div>
            <label class="block font-medium">Encounter Date</label>
            <input v-model="claim.encounter_date" type="date" class="w-full border rounded p-2" required />
          </div>

          <div>
            <label class="block font-medium">Specialty</label>
            <select v-model="claim.specialty" class="w-full border rounded p-2">
              <option v-for="specialty in specialties" :key="specialty" :value="specialty">
                {{ specialty }}
              </option>
            </select>
          </div>

          <div>
            <label class="block font-medium">Priority Level</label>
            <select v-model="claim.priority_level" class="w-full border rounded p-2">
              <option v-for="level in [1, 2, 3, 4, 5]" :key="level" :value="level">{{ level }}</option>
            </select>
          </div>
        </div>

        <div class="mt-6">
          <h3 class="font-bold mb-2">Claim Items</h3>
          <table class="w-full border-collapse border border-gray-300">
            <thead>
              <tr class="bg-gray-200">
                <th class="border p-2">Item Name</th>
                <th class="border p-2">Unit Price</th>
                <th class="border p-2">Quantity</th>
                <th class="border p-2">Subtotal</th>
                <th class="border p-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in claim.items" :key="index">
                <td class="border p-2">
                  <input v-model="item.name" type="text" class="w-full border p-2" required />
                </td>
                <td class="border p-2">
                  <input v-model.number="item.unit_price" type="number" min="0" class="w-full border p-2" required />
                </td>
                <td class="border p-2">
                  <input v-model.number="item.quantity" type="number" min="1" class="w-full border p-2" required />
                </td>
                <td class="border p-2">{{ item.unit_price * item.quantity }}</td>
                <td class="border p-2 text-center">
                  <button @click.prevent="removeItem(index)" class="text-red-500">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
          <button @click.prevent="addItem" class="mt-2 bg-blue-500 text-white px-3 py-1 rounded">Add Item</button>
        </div>

        <div class="mb-4 mt-8">
          <label class="block font-medium">Total Amount</label>
          <input type="number" class="w-full border p-2 bg-gray-100" :value="totalAmount" readonly />
        </div>

        <div class="mt-6 flex justify-center">
          <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded">
            Submit Claim
          </button>
        </div>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";

console.log('submit claim page loaded')

</script>
<script>
export default {
  data() {
    return {
      claim: {
        insurer_code: '',
        provider_name: '',
        encounter_date: '',
        specialty: '',
        priority_level: 1,
        items: []
      },
      specialties: ['Cardiology', 'Orthopedics', 'Neurology', 'Oncology', 'Pediatrics']
    };
  },
  computed: {
    totalAmount() {
      return this.claim.items.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    }
  },
  methods: {
    addItem() {
      this.claim.items.push({ name: '', unit_price: 0, quantity: 1 });
    },
    removeItem(index) {
      this.claim.items.splice(index, 1);
    },
    async submitClaim() {
      try {
        const claimPayload = {
          ...this.claim,
          total_amount: this.totalAmount
        };
        const response = await axios.post('/api/claim', claimPayload);
        alert('Claim submitted successfully!');
        console.log('Response:', response.data);
      } catch (error) {
        console.error('Error submitting claim:', error.response.data.message);
        let msg = error.response.data.message;
        alert('Failed to submit claim: ' + msg);
      }
    }
  }
};
</script>