import { ref } from 'vue';

export function useCompanies() {
    const companies = ref([]);
    const loading = ref(false);
    const error = ref('');

    async function loadCompanies() {
        loading.value = true;
        error.value = '';

        try {
            const response = await fetch('/api/companies');
            if (!response.ok) throw new Error('Unable to load companies.');
            companies.value = await response.json();
        } catch (exception) {
            error.value = exception.message;
        } finally {
            loading.value = false;
        }
    }

    return { companies, loading, error, loadCompanies };
}
