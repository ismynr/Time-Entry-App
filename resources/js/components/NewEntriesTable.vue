<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import FieldError from './FieldError.vue';
import SearchSelect from './SearchSelect.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    companies: { type: Array, required: true },
    selectedCompanyId: { type: [String, Number], default: '' },
    getLookups: { type: Function, required: true },
    loadLookups: { type: Function, required: true },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['add-row', 'add-from-previous', 'add-next-from-row', 'remove-row', 'duplicate-row', 'clear-row', 'submit']);
const tableRoot = ref(null);

watch(() => props.rows.map((row) => row.company_id), (companyIds) => {
    companyIds.filter(Boolean).forEach((companyId) => props.loadLookups(companyId));
}, { immediate: true, deep: true });

const totalHours = computed(() => props.rows.reduce((sum, row) => sum + (Number(row.hours) || 0), 0).toFixed(2));
const rowTotals = computed(() => {
    const totals = {};

    props.rows.forEach((row) => {
        if (!row.employee_id || !row.entry_date) return;
        const key = `${row.employee_id}|${row.entry_date}`;
        totals[key] = (totals[key] || 0) + (Number(row.hours) || 0);
    });

    return totals;
});
const totalGroups = computed(() => Object.entries(rowTotals.value).map(([key, hours]) => {
    const [employeeId, date] = key.split('|');
    const employee = props.rows
        .map((row) => props.getLookups(row.company_id).employees.find((option) => String(option.id) === String(employeeId)))
        .find(Boolean);

    return {
        key,
        label: `${employee?.name ?? 'Employee'} · ${date}`,
        hours: hours.toFixed(2),
    };
}));
const errorSummary = computed(() => Object.entries(props.errors).map(([key, messages]) => {
    const match = key.match(/^entries\.(\d+)\.(.+)$/);

    return {
        key,
        row: match ? Number(match[1]) + 1 : null,
        field: match ? match[2].replace('_id', '').replace('_', ' ') : key,
        message: Array.isArray(messages) ? messages[0] : messages,
    };
}));
const summarySections = computed(() => {
    const sections = {
        company: new Map(),
        employee: new Map(),
        project: new Map(),
        task: new Map(),
        date: new Map(),
    };

    props.rows.forEach((row) => {
        const hours = Number(row.hours) || 0;
        if (!hours) return;

        addSummary(sections.date, row.entry_date, row.entry_date, hours);
        addSummary(sections.company, row.company_id, findName(props.companies, row.company_id), hours);
        addSummary(sections.employee, row.employee_id, findName(props.getLookups(row.company_id).employees, row.employee_id), hours);
        addSummary(sections.project, row.project_id, findName(props.getLookups(row.company_id).projects, row.project_id), hours);
        addSummary(sections.task, row.task_id, findName(props.getLookups(row.company_id).tasks, row.task_id), hours);
    });

    return Object.entries(sections).map(([label, values]) => ({
        label,
        values: Array.from(values.values()).sort((a, b) => a.name.localeCompare(b.name)),
    }));
});

function fieldError(index, field) {
    return props.errors[`entries.${index}.${field}`] || '';
}

function hasFieldError(index, field) {
    return Boolean(fieldError(index, field));
}

function onCompanyChange(row) {
    row.employee_id = '';
    row.project_id = '';
    row.task_id = '';
    props.loadLookups(row.company_id);
}

function onEmployeeChange(row) {
    row.project_id = '';
}

function availableProjects(row) {
    return props.getLookups(row.company_id).projects.filter((project) => {
        return !row.employee_id || (project.employee_ids || []).includes(Number(row.employee_id));
    });
}

function onHoursEnter(index) {
    if (index === props.rows.length - 1) emit('add-next-from-row', index);
}

function findName(options, id) {
    return options.find((option) => String(option.id) === String(id))?.name || '';
}

function addSummary(map, id, name, hours) {
    if (!id || !name) return;
    const key = String(id);
    const current = map.get(key) || { name, hours: 0 };
    current.hours += hours;
    map.set(key, current);
}

async function focusFirstError() {
    await nextTick();
    tableRoot.value?.querySelector('.invalid-field')?.focus();
}

defineExpose({ focusFirstError });
</script>

<template>
    <section ref="tableRoot" class="panel">
        <div class="panel-header">
            <div>
                <h2>New Entries</h2>
                <p>{{ rows.length }} rows · {{ totalHours }} hours</p>
            </div>
            <div class="panel-actions">
                <button type="button" @click="emit('add-from-previous')">Add from previous</button>
                <button type="button" class="primary" @click="emit('add-row')">Add blank row</button>
            </div>
        </div>

        <div v-if="errorSummary.length" class="validation-summary">
            <strong>Review {{ errorSummary.length }} validation issue{{ errorSummary.length === 1 ? '' : 's' }}</strong>
            <ul>
                <li v-for="error in errorSummary" :key="error.key">
                    Row {{ error.row ?? '-' }}, {{ error.field }}: {{ error.message }}
                </li>
            </ul>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Company</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th>Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="index">
                        <td>{{ index + 1 }}</td>
                        <td>
                            <SearchSelect v-model="row.company_id" :class="{ 'invalid-field': hasFieldError(index, 'company_id') }" :options="companies" :disabled="Boolean(selectedCompanyId)" @update:model-value="onCompanyChange(row)" />
                            <FieldError :message="fieldError(index, 'company_id')" />
                        </td>
                        <td>
                            <input v-model="row.entry_date" :class="{ 'invalid-field': hasFieldError(index, 'entry_date') }" type="date">
                            <FieldError :message="fieldError(index, 'entry_date')" />
                        </td>
                        <td>
                            <SearchSelect v-model="row.employee_id" :class="{ 'invalid-field': hasFieldError(index, 'employee_id') }" :options="getLookups(row.company_id).employees" :disabled="!row.company_id" @update:model-value="onEmployeeChange(row)" />
                            <FieldError :message="fieldError(index, 'employee_id')" />
                        </td>
                        <td>
                            <SearchSelect v-model="row.project_id" :class="{ 'invalid-field': hasFieldError(index, 'project_id') }" :options="availableProjects(row)" :disabled="!row.company_id || !row.employee_id" />
                            <FieldError :message="fieldError(index, 'project_id')" />
                        </td>
                        <td>
                            <SearchSelect v-model="row.task_id" :class="{ 'invalid-field': hasFieldError(index, 'task_id') }" :options="getLookups(row.company_id).tasks" :disabled="!row.company_id" />
                            <FieldError :message="fieldError(index, 'task_id')" />
                        </td>
                        <td>
                            <input v-model="row.hours" :class="{ 'invalid-field': hasFieldError(index, 'hours') }" type="number" min="0" max="24" step="0.25" @focus="$event.target.select()" @keydown.enter.prevent="onHoursEnter(index)">
                            <p v-if="row.employee_id && row.entry_date" class="row-total">
                                Day total: {{ rowTotals[`${row.employee_id}|${row.entry_date}`].toFixed(2) }}
                            </p>
                            <FieldError :message="fieldError(index, 'hours')" />
                        </td>
                        <td class="actions">
                            <button type="button" title="Duplicate row" @click="emit('duplicate-row', index)">Copy</button>
                            <button type="button" title="Clear row" @click="emit('clear-row', index)">Clear</button>
                            <button type="button" title="Remove row" @click="emit('remove-row', index)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="submit-bar">
            <div class="total-groups">
                <span v-for="group in totalGroups" :key="group.key">{{ group.label }}: {{ group.hours }}h</span>
            </div>
            <button type="button" class="primary" :disabled="submitting" @click="emit('submit')">
                {{ submitting ? 'Saving...' : 'Submit entries' }}
            </button>
        </div>

        <div class="summary-grid">
            <section v-for="section in summarySections" :key="section.label">
                <h3>{{ section.label }}</h3>
                <p v-if="section.values.length === 0">No hours yet</p>
                <p v-for="item in section.values" :key="item.name">
                    <span>{{ item.name }}</span>
                    <strong>{{ item.hours.toFixed(2) }}h</strong>
                </p>
            </section>
        </div>
    </section>
</template>
