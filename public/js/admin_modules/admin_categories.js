/**
 * RePlate Admin Food Categories Module
 */
async function loadCategories() {
    const tbody = document.getElementById('categoriesTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading categories...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=categories');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.categories || data.categories.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="table-empty">No categories available. Click "Add Category" to create one.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.categories.map(c => `
            <tr>
                <td>#${c.category_id}</td>
                <td><strong>${escapeHTML(c.name || '')}</strong></td>
                <td>${escapeHTML(c.description || 'No description provided')}</td>
                <td><span class="badge badge-info">${c.listings_count || 0} donations</span></td>
                <td>
                    <button type="button" class="btn-sm btn-danger delete-category-btn" data-id="${c.category_id}" data-name="${escapeHTML(c.name || '')}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');

        tbody.onclick = (e) => {
            const btn = e.target.closest('.delete-category-btn');
            if (btn) {
                const catId = btn.getAttribute('data-id');
                const catName = btn.getAttribute('data-name') || 'Category';
                confirmDeleteCategory(catId, catName);
            }
        };
    } catch (err) {
        console.error('Failed to load categories:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty text-danger">Failed to load categories.</td></tr>`;
    }
}

async function handleAddCategorySubmit(e) {
    e.preventDefault();
    const nameInput = document.getElementById('catName');
    const descInput = document.getElementById('catDesc');

    if (!nameInput || !nameInput.value.trim()) {
        showToast('Category name is required.', 'warning');
        return;
    }

    try {
        const res = await fetch('/api/admin?action=add_category', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: nameInput.value.trim(),
                description: descInput ? descInput.value.trim() : ''
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Category added successfully.', 'success');
            toggleModal('addCategoryModal', false);
            nameInput.value = '';
            if (descInput) descInput.value = '';
            loadCategories();
        } else {
            showToast(data.error || 'Failed to add category.', 'danger');
        }
    } catch (err) {
        showToast('Network error creating food category.', 'danger');
    }
}

function confirmDeleteCategory(categoryId, categoryName) {
    showConfirmModal(
        'Delete Food Category',
        `Are you sure you want to delete category "${categoryName}"? Existing donations linked to it may lose classification.`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=delete_category', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category_id: categoryId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Category deleted.', 'success');
                    loadCategories();
                } else {
                    showToast(data.error || 'Failed to delete category.', 'danger');
                }
            } catch (err) {
                showToast('Network error deleting category.', 'danger');
            }
        }
    );
}