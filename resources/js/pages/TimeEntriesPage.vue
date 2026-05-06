<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import CompanyFilter from '../components/CompanyFilter.vue';
import HistoryTable from '../components/HistoryTable.vue';
import NewEntriesTable from '../components/NewEntriesTable.vue';
import Tabs from '../components/Tabs.vue';
import { useCompanies } from '../composables/useCompanies';
import { useCompanyLookups } from '../composables/useCompanyLookups';
import { useTimeEntryHistory } from '../composables/useTimeEntryHistory';
import { useTimeEntryRows } from '../composables/useTimeEntryRows';

const selectedCompanyId = ref('');
const activeTab = ref('new');
const serverErrors = ref({});
const notice = ref('');
const submitting = ref(false);
const newEntriesTable = ref(null);

const { companies, loading: companiesLoading, error: companiesError, loadCompanies } = useCompanies();
const { loadCompanyLookups, getCompanyLookups } = useCompanyLookups();
const rowState = useTimeEntryRows(selectedCompanyId);
const history = useTimeEntryHistory(selectedCompanyId);

const tabs = [
    { value: 'new', label: 'New Entries' },
    { value: 'history', label: 'History' },
];

const currentLookups = computed(() => {
    if (selectedCompanyId.value) {
        return getCompanyLookups(selectedCompanyId.value);
    }

    const employees = new Map();
    const projects = new Map();

    companies.value.forEach((company) => {
        const lookups = getCompanyLookups(company.id);
        lookups.employees.forEach((employee) => employees.set(employee.id, employee));
        lookups.projects.forEach((project) => projects.set(project.id, project));
    });

    return {
        employees: Array.from(employees.values()).sort((a, b) => a.name.localeCompare(b.name)),
        projects: Array.from(projects.values()).sort((a, b) => a.name.localeCompare(b.name)),
    };
});

watch(selectedCompanyId, async (companyId) => {
    serverErrors.value = {};
    rowState.applyCompanyFilter(companyId);

    if (companyId) await loadCompanyLookups(companyId);

    if (activeTab.value === 'history') {
        await loadHistoryLookups();
        history.loadHistory();
    }
});

watch(activeTab, (tab) => {
    if (tab === 'history') {
        loadHistoryLookups();
        history.loadHistory();
    }
});

watch(() => history.filters.value.search, () => {
    if (activeTab.value === 'history') history.loadHistory();
});

onMounted(async () => {
    await loadCompanies();
    window.addEventListener('keydown', handleShortcut);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleShortcut);
});

async function loadHistoryLookups() {
    if (selectedCompanyId.value) {
        await loadCompanyLookups(selectedCompanyId.value);
        return;
    }

    await Promise.all(companies.value.map((company) => loadCompanyLookups(company.id)));
}

async function submitRows() {
    serverErrors.value = {};
    notice.value = '';

    if (rowState.payloadRows.value.length === 0) {
        notice.value = 'Add at least one row before submitting.';
        return;
    }

    submitting.value = true;

    try {
        const response = await fetch('/api/time-entries/bulk', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ entries: rowState.payloadRows.value }),
        });

        const payload = await response.json();

        if (!response.ok) {
            serverErrors.value = payload.errors || {};
            notice.value = payload.message || 'Please review the highlighted rows.';
            await nextTick();
            newEntriesTable.value?.focusFirstError();
            return;
        }

        notice.value = payload.message;
        rowState.rows.value = [{ ...rowState.rows.value.at(-1), task_id: '', hours: '' }];
        history.loadHistory();
    } catch (exception) {
        notice.value = exception.message;
    } finally {
        submitting.value = false;
    }
}

function handleShortcut(event) {
    const target = event.target;
    const isTextInput = ['INPUT', 'SELECT', 'TEXTAREA'].includes(target?.tagName);

    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        if (activeTab.value === 'new') submitRows();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'n') {
        event.preventDefault();
        if (activeTab.value === 'new') rowState.addFromPrevious();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'd') {
        event.preventDefault();
        if (activeTab.value === 'new') rowState.duplicateLastRow();
        return;
    }

    if (event.key === 'Escape' && !isTextInput) {
        notice.value = '';
    }
}

function sortHistory(column) {
    if (history.filters.value.sort === column) {
        history.filters.value.direction = history.filters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        history.filters.value.sort = column;
        history.filters.value.direction = column === 'date' || column === 'created_at' ? 'desc' : 'asc';
    }

    history.loadHistory();
}

function onHistorySaved(message) {
    notice.value = message;
    history.loadHistory(history.meta.value?.current_page || 1);
}
</script>

<template>
    <main class="app-shell">
        <header class="topbar">
            <div>
                <h1>Time Entry App</h1>
                <p>Bulk time logging with company-scoped employees, projects, and tasks.</p>
            </div>
            <CompanyFilter v-model="selectedCompanyId" :companies="companies" :loading="companiesLoading" />
        </header>

        <p v-if="companiesError" class="banner error">{{ companiesError }}</p>
        <p v-if="notice" class="banner">{{ notice }}</p>

        <Tabs v-model="activeTab" :tabs="tabs" />

        <NewEntriesTable
            v-if="activeTab === 'new'"
            ref="newEntriesTable"
            :rows="rowState.rows.value"
            :companies="companies"
            :selected-company-id="selectedCompanyId"
            :get-lookups="getCompanyLookups"
            :load-lookups="loadCompanyLookups"
            :errors="serverErrors"
            :submitting="submitting"
            @add-row="rowState.addRow"
            @add-from-previous="rowState.addFromPrevious"
            @add-next-from-row="rowState.addNextFromRow"
            @remove-row="rowState.removeRow"
            @duplicate-row="rowState.duplicateRow"
            @clear-row="rowState.clearRow"
            @submit="submitRows"
        />

        <section v-else>
            <div class="filters">
                <label class="field">
                    <span>Search</span>
                    <input v-model="history.filters.value.search" type="search" placeholder="Company, employee, project, task">
                </label>
                <label class="field">
                    <span>From</span>
                    <input v-model="history.filters.value.date_from" type="date" @change="history.loadHistory()">
                </label>
                <label class="field">
                    <span>To</span>
                    <input v-model="history.filters.value.date_to" type="date" @change="history.loadHistory()">
                </label>
                <label class="field">
                    <span>Employee</span>
                    <select v-model="history.filters.value.employee_id" @change="history.loadHistory()">
                        <option value="">All</option>
                        <option v-for="employee in currentLookups.employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </select>
                </label>
                <label class="field">
                    <span>Project</span>
                    <select v-model="history.filters.value.project_id" @change="history.loadHistory()">
                        <option value="">All</option>
                        <option v-for="project in currentLookups.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </label>
            </div>
            <p v-if="history.error.value" class="banner error">{{ history.error.value }}</p>
            <HistoryTable
                :entries="history.entries.value"
                :meta="history.meta.value"
                :loading="history.loading.value"
                :companies="companies"
                :get-lookups="getCompanyLookups"
                :load-lookups="loadCompanyLookups"
                :sort="history.filters.value.sort"
                :direction="history.filters.value.direction"
                @page="history.loadHistory"
                @sort="sortHistory"
                @saved="onHistorySaved"
                @error="notice = $event"
            />
        </section>
    </main>
</template>
