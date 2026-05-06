import { ref } from 'vue';

export function useTimeEntryHistory(selectedCompanyId) {
    const entries = ref([]);
    const meta = ref(null);
    const loading = ref(false);
    const error = ref('');
    const filters = ref({
        date_from: '',
        date_to: '',
        employee_id: '',
        project_id: '',
        search: '',
        sort: 'date',
        direction: 'desc',
    });

    async function loadHistory(page = 1) {
        loading.value = true;
        error.value = '';

        const params = new URLSearchParams({ page, per_page: 10 });
        if (selectedCompanyId.value) params.set('company_id', selectedCompanyId.value);

        Object.entries(filters.value).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        try {
            const response = await fetch(`/api/time-entries?${params}`);
            if (!response.ok) throw new Error('Unable to load history.');
            const payload = await response.json();
            entries.value = payload.data;
            meta.value = payload;
        } catch (exception) {
            error.value = exception.message;
        } finally {
            loading.value = false;
        }
    }

    return { entries, meta, loading, error, filters, loadHistory };
}
