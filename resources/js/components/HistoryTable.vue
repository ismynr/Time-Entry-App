<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import SearchSelect from './SearchSelect.vue';

const props = defineProps({
    entries: { type: Array, required: true },
    meta: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    companies: { type: Array, required: true },
    getLookups: { type: Function, required: true },
    loadLookups: { type: Function, required: true },
    sort: { type: String, default: 'date' },
    direction: { type: String, default: 'desc' },
});

const emit = defineEmits(['page', 'sort', 'saved', 'error']);
const editingId = ref(null);
const editRow = reactive(blankEditRow());
const editErrors = ref({});
const saving = ref(false);

watch(() => editRow.company_id, (companyId) => {
    if (companyId) props.loadLookups(companyId);
});

const editProjects = computed(() => props.getLookups(editRow.company_id).projects.filter((project) => {
    return !editRow.employee_id || (project.employee_ids || []).includes(Number(editRow.employee_id));
}));

const sortableColumns = [
    { label: 'Company', value: 'company' },
    { label: 'Date', value: 'date' },
    { label: 'Employee', value: 'employee' },
    { label: 'Project', value: 'project' },
    { label: 'Task', value: 'task' },
    { label: 'Hours', value: 'hours' },
    { label: 'Created at', value: 'created_at' },
];
const pageTotals = computed(() => {
    const sections = {
        company: new Map(),
        employee: new Map(),
        project: new Map(),
        task: new Map(),
        date: new Map(),
    };

    props.entries.forEach((entry) => {
        const hours = Number(entry.hours) || 0;
        addSummary(sections.company, entry.company.id, entry.company.name, hours);
        addSummary(sections.employee, entry.employee.id, entry.employee.name, hours);
        addSummary(sections.project, entry.project.id, entry.project.name, hours);
        addSummary(sections.task, entry.task.id, entry.task.name, hours);
        addSummary(sections.date, entry.entry_date, entry.entry_date, hours);
    });

    return Object.entries(sections).map(([label, values]) => ({
        label,
        values: Array.from(values.values()).sort((a, b) => a.name.localeCompare(b.name)),
    }));
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

function blankEditRow() {
    return { company_id: '', entry_date: '', employee_id: '', project_id: '', task_id: '', hours: '' };
}

function startEdit(entry) {
    editingId.value = entry.id;
    editErrors.value = {};
    Object.assign(editRow, {
        company_id: entry.company.id,
        entry_date: entry.entry_date,
        employee_id: entry.employee.id,
        project_id: entry.project.id,
        task_id: entry.task.id,
        hours: entry.hours,
    });
    props.loadLookups(entry.company.id);
}

function cancelEdit() {
    editingId.value = null;
    editErrors.value = {};
    Object.assign(editRow, blankEditRow());
}

function onCompanyChange() {
    editRow.employee_id = '';
    editRow.project_id = '';
    editRow.task_id = '';
}

function onEmployeeChange() {
    editRow.project_id = '';
}

function fieldError(field) {
    return editErrors.value[field]?.[0] || '';
}

async function saveEdit(entry) {
    saving.value = true;
    editErrors.value = {};

    try {
        const response = await fetch(`/api/time-entries/${entry.id}`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(editRow),
        });
        const payload = await response.json();

        if (!response.ok) {
            editErrors.value = payload.errors || {};
            emit('error', payload.message || 'Please review the edit fields.');
            return;
        }

        cancelEdit();
        emit('saved', payload.message);
    } catch (exception) {
        emit('error', exception.message);
    } finally {
        saving.value = false;
    }
}

function sortColumn(column) {
    emit('sort', column);
}

function addSummary(map, id, name, hours) {
    if (!id || !name) return;
    const key = String(id);
    const current = map.get(key) || { name, hours: 0 };
    current.hours += hours;
    map.set(key, current);
}

function handleKeydown(event) {
    if (event.key === 'Escape' && editingId.value) {
        event.preventDefault();
        cancelEdit();
    }
}
</script>

<template>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>History</h2>
                <p>{{ meta?.total ?? 0 }} saved entries</p>
            </div>
        </div>

        <div v-if="loading" class="empty-state">Loading history...</div>
        <div v-else-if="entries.length === 0" class="empty-state">No time entries found.</div>
        <div v-else class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th v-for="column in sortableColumns" :key="column.value">
                            <button type="button" class="sort-button" @click="sortColumn(column.value)">
                                {{ column.label }}
                                <span v-if="sort === column.value">{{ direction === 'asc' ? 'ASC' : 'DESC' }}</span>
                            </button>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in entries" :key="entry.id">
                        <template v-if="editingId === entry.id">
                            <td>
                                <SearchSelect v-model="editRow.company_id" :options="companies" @update:model-value="onCompanyChange" />
                                <p v-if="fieldError('company_id')" class="field-error">{{ fieldError('company_id') }}</p>
                            </td>
                            <td>
                                <input v-model="editRow.entry_date" type="date">
                                <p v-if="fieldError('entry_date')" class="field-error">{{ fieldError('entry_date') }}</p>
                            </td>
                            <td>
                                <SearchSelect v-model="editRow.employee_id" :options="getLookups(editRow.company_id).employees" @update:model-value="onEmployeeChange" />
                                <p v-if="fieldError('employee_id')" class="field-error">{{ fieldError('employee_id') }}</p>
                            </td>
                            <td>
                                <SearchSelect v-model="editRow.project_id" :options="editProjects" :disabled="!editRow.employee_id" />
                                <p v-if="fieldError('project_id')" class="field-error">{{ fieldError('project_id') }}</p>
                            </td>
                            <td>
                                <SearchSelect v-model="editRow.task_id" :options="getLookups(editRow.company_id).tasks" />
                                <p v-if="fieldError('task_id')" class="field-error">{{ fieldError('task_id') }}</p>
                            </td>
                            <td>
                                <input v-model="editRow.hours" type="number" min="0" max="24" step="0.25">
                                <p v-if="fieldError('hours')" class="field-error">{{ fieldError('hours') }}</p>
                            </td>
                            <td>{{ new Date(entry.created_at).toLocaleString() }}</td>
                            <td class="actions">
                                <button type="button" class="primary" :disabled="saving" @click="saveEdit(entry)">Save</button>
                                <button type="button" @click="cancelEdit">Cancel</button>
                            </td>
                        </template>
                        <template v-else>
                            <td>{{ entry.company.name }}</td>
                            <td>{{ entry.entry_date }}</td>
                            <td>{{ entry.employee.name }}</td>
                            <td>{{ entry.project.name }}</td>
                            <td>{{ entry.task.name }}</td>
                            <td>{{ entry.hours }}</td>
                            <td>{{ new Date(entry.created_at).toLocaleString() }}</td>
                            <td class="actions">
                                <button type="button" @click="startEdit(entry)">Edit</button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="pagination">
            <button type="button" :disabled="meta.current_page === 1" @click="emit('page', meta.current_page - 1)">Previous</button>
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button type="button" :disabled="meta.current_page === meta.last_page" @click="emit('page', meta.current_page + 1)">Next</button>
        </div>

        <div class="summary-grid">
            <section v-for="section in pageTotals" :key="section.label">
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
