<script setup>
defineProps({
    message: { type: [String, Array], default: '' },
});
</script>

<template>
    <p v-if="message" class="field-error">
        {{ Array.isArray(message) ? message[0] : message }}
    </p>
</template>
