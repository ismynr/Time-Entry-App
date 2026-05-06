<script setup>
defineProps({
    companies: { type: Array, required: true },
    loading: { type: Boolean, default: false },
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <label class="field compact">
        <span>Company</span>
        <select v-model="model" :disabled="loading">
            <option value="">All companies</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
            </option>
        </select>
    </label>
</template>
