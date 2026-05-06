<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'Select' },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: [String, Number], default: '' });
const text = ref('');
const listId = `search-select-${Math.random().toString(36).slice(2)}`;

const selectedOption = computed(() => props.options.find((option) => String(option.id) === String(model.value)));

watch(selectedOption, (option) => {
    text.value = option?.name ?? '';
}, { immediate: true });

watch(() => props.options, () => {
    text.value = selectedOption.value?.name ?? '';
});

function onInput() {
    const match = props.options.find((option) => option.name.toLowerCase() === text.value.trim().toLowerCase());
    model.value = match?.id ?? '';
}
</script>

<template>
    <input
        v-model="text"
        type="text"
        :list="listId"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @input="onInput"
    >
    <datalist :id="listId">
        <option v-for="option in options" :key="option.id" :value="option.name" />
    </datalist>
</template>
