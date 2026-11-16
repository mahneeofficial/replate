/**
 * RePlate Donations Categories Loader
 * Location: public/js/donations_modules/donations_categories.js
 */

async function loadCategories() {
    const select = document.getElementById('page_category_id');
    if (!select) return;

    try {
        let res = await fetch('/api/categories.php');
        if (!res.ok) {
            res = await fetch('/api/categories');
        }
        if (!res.ok) return;

        const data = await res.json();
        const categories = data.categories || (Array.isArray(data) ? data : []);

        if (categories.length > 0) {
            select.innerHTML = '<option value="">Select Category</option>' + 
                categories.map(c => `<option value="${c.category_id || c.id}">${escapeHTML(c.name)}</option>`).join('');
        }
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}