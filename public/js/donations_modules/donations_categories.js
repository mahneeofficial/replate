/**
 * RePlate Donations Categories Loader
 * Location: public/js/donations_modules/donations_categories.js
 */

async function loadCategories() {
    const createSelect = document.getElementById('page_category_id');
    const filterSelect = document.getElementById('filterCategorySelect');
    const editSelect = document.getElementById('edit_category_id');

    if (!createSelect && !filterSelect && !editSelect) return;

    try {
        let res = await fetch('/api/categories.php');
        if (!res.ok) {
            res = await fetch('/api/categories');
        }
        if (!res.ok) return;

        const data = await res.json();
        const categories = data.categories || (Array.isArray(data) ? data : []);

        if (categories.length > 0) {
            const optionsMarkup = categories
                .map(c => `<option value="${c.category_id || c.id}">${escapeHTML(c.name)}</option>`)
                .join('');

            if (createSelect) {
                createSelect.innerHTML = '<option value="">Select Category...</option>' + optionsMarkup;
            }
            if (filterSelect) {
                filterSelect.innerHTML = '<option value="">All Categories</option>' + optionsMarkup;
            }
            if (editSelect) {
                editSelect.innerHTML = '<option value="">Select Category...</option>' + optionsMarkup;
            }
        }
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}