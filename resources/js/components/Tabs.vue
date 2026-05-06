<script setup>
defineProps({
    tabs: { type: Array, required: true },
});

const model = defineModel({ type: String, required: true });
</script>

<template>
    <div class="tabs" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            :class="{ active: model === tab.value }"
            @click="model = tab.value"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
