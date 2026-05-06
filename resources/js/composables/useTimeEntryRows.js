import { computed, ref } from 'vue';

function blankRow(companyId = '') {
    return {
        company_id: companyId,
        entry_date: new Date().toISOString().slice(0, 10),
        employee_id: '',
        project_id: '',
        task_id: '',
        hours: '',
    };
}

function nextRowFrom(row) {
    return {
        company_id: row.company_id,
        entry_date: row.entry_date,
        employee_id: row.employee_id,
        project_id: row.project_id,
        task_id: '',
        hours: '',
    };
}

export function useTimeEntryRows(selectedCompanyId) {
    const rows = ref([blankRow(selectedCompanyId.value || '')]);

    function addRow(source = null) {
        rows.value.push(source ? { ...source } : blankRow(selectedCompanyId.value || ''));
    }

    function insertRow(row) {
        const firstRow = rows.value[0];
        const firstRowIsBlank = rows.value.length === 1 && !firstRow.company_id && !firstRow.employee_id && !firstRow.project_id && !firstRow.task_id && !firstRow.hours;

        if (firstRowIsBlank) {
            rows.value[0] = { ...blankRow(selectedCompanyId.value || ''), ...row };
            return;
        }

        addRow(row);
    }

    function addFromPrevious() {
        const previous = rows.value.at(-1);
        addRow(previous ? nextRowFrom(previous) : null);
    }

    function removeRow(index) {
        rows.value.splice(index, 1);
        if (rows.value.length === 0) addRow();
    }

    function duplicateRow(index) {
        addRow(rows.value[index]);
    }

    function duplicateLastRow() {
        duplicateRow(rows.value.length - 1);
    }

    function clearRow(index) {
        rows.value[index] = blankRow(selectedCompanyId.value || '');
    }

    function applyCompanyFilter(companyId) {
        rows.value = rows.value.map((row) => ({
            ...row,
            company_id: companyId || row.company_id,
            employee_id: companyId && Number(row.company_id) !== Number(companyId) ? '' : row.employee_id,
            project_id: companyId && Number(row.company_id) !== Number(companyId) ? '' : row.project_id,
            task_id: companyId && Number(row.company_id) !== Number(companyId) ? '' : row.task_id,
        }));
    }

    const payloadRows = computed(() => rows.value.filter((row) => {
        return row.company_id || row.employee_id || row.project_id || row.task_id || row.hours;
    }));

    function addNextFromRow(index) {
        addRow(nextRowFrom(rows.value[index]));
    }

    return {
        rows,
        payloadRows,
        addRow,
        insertRow,
        addFromPrevious,
        addNextFromRow,
        removeRow,
        duplicateRow,
        duplicateLastRow,
        clearRow,
        applyCompanyFilter,
    };
}
