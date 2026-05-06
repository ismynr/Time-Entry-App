<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    selectedCompanyId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['insert-row', 'notice']);
const text = ref('Ari Wijaya worked on Platform Build 1 on 01/01/2026 doing Development for 4 hours.');
const loading = ref(false);
const result = ref(null);
const error = ref('');

const canInsert = computed(() => {
    const row = result.value?.row;
    return row?.entry_date && row?.hours;
});

async function parseEntry() {
    loading.value = true;
    result.value = null;
    error.value = '';

    try {
        const response = await fetch('/api/ai/time-entry/parse', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                text: text.value,
                company_id: props.selectedCompanyId || null,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            error.value = payload.message || 'Unable to parse this entry.';
            return;
        }

        result.value = payload;
    } catch (exception) {
        error.value = exception.message;
    } finally {
        loading.value = false;
    }
}

function insertRow() {
    if (!result.value?.row) return;

    emit('insert-row', {
        company_id: result.value.row.company_id || '',
        entry_date: result.value.row.entry_date || new Date().toISOString().slice(0, 10),
        employee_id: result.value.row.employee_id || '',
        project_id: result.value.row.project_id || '',
        task_id: result.value.row.task_id || '',
        hours: result.value.row.hours || '',
    });
    emit('notice', 'AI draft inserted. Review the row before submitting.');
    result.value = null;
}
</script>

<template>
    <section class="panel ai-assistant">
        <div class="panel-header">
            <div>
                <h2>AI-Assisted Entry</h2>
                <p>Parse one sentence into a draft row, then confirm before inserting.</p>
            </div>
            <button type="button" class="primary" :disabled="loading || !text.trim()" @click="parseEntry">
                {{ loading ? 'Parsing...' : 'Parse entry' }}
            </button>
        </div>

        <div class="ai-body">
            <textarea
                v-model="text"
                rows="3"
                placeholder="John worked on Project X on 01/01/2026 doing cleanup for 4 hours."
            />

            <p v-if="error" class="banner error">{{ error }}</p>

            <div v-if="result" class="ai-preview">
                <div>
                    <h3>Review parsed draft</h3>
                    <p>Provider: {{ result.provider }} · Confidence: {{ Math.round((result.parsed.confidence || 0) * 100) }}%</p>
                    <p v-if="result.parsed.notes">{{ result.parsed.notes }}</p>
                </div>

                <dl>
                    <div>
                        <dt>Company</dt>
                        <dd>{{ result.matched.company?.name || 'Needs review' }}</dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd>{{ result.row.entry_date || 'Needs review' }}</dd>
                    </div>
                    <div>
                        <dt>Employee</dt>
                        <dd>{{ result.matched.employee?.name || result.parsed.employee_name || 'Needs review' }}</dd>
                    </div>
                    <div>
                        <dt>Project</dt>
                        <dd>{{ result.matched.project?.name || result.parsed.project_name || 'Needs review' }}</dd>
                    </div>
                    <div>
                        <dt>Task</dt>
                        <dd>{{ result.matched.task?.name || result.parsed.task_name || 'Needs review' }}</dd>
                    </div>
                    <div>
                        <dt>Hours</dt>
                        <dd>{{ result.row.hours || 'Needs review' }}</dd>
                    </div>
                </dl>

                <p v-if="result.missing_fields.length" class="banner">
                    Needs manual review: {{ result.missing_fields.join(', ') }}.
                </p>

                <div class="panel-actions">
                    <button type="button" class="primary" :disabled="!canInsert" @click="insertRow">Insert row</button>
                    <button type="button" @click="result = null">Cancel</button>
                </div>
            </div>
        </div>
    </section>
</template>
