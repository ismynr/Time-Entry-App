import { reactive, ref } from 'vue';

const cache = reactive({});

export function useCompanyLookups() {
    const loading = ref({});
    const errors = ref({});

    async function loadCompanyLookups(companyId) {
        if (!companyId || cache[companyId]) return cache[companyId] ?? emptyLookups();

        loading.value = { ...loading.value, [companyId]: true };
        errors.value = { ...errors.value, [companyId]: '' };

        try {
            const response = await fetch(`/api/companies/${companyId}/lookups`);
            if (!response.ok) throw new Error('Unable to load company options.');
            cache[companyId] = await response.json();
        } catch (exception) {
            errors.value = { ...errors.value, [companyId]: exception.message };
            cache[companyId] = emptyLookups();
        } finally {
            loading.value = { ...loading.value, [companyId]: false };
        }

        return cache[companyId];
    }

    function getCompanyLookups(companyId) {
        return cache[companyId] ?? emptyLookups();
    }

    return { loading, errors, loadCompanyLookups, getCompanyLookups };
}

function emptyLookups() {
    return { employees: [], projects: [], tasks: [] };
}
