/**
 * RePlate Activity Log Controller
 * Location: public/js/activity.js
 */

document.addEventListener('DOMContentLoaded', async () => {
    await fetchAndRenderActivityLogs();
});

async function fetchAndRenderActivityLogs() {
    const container = document.getElementById('activityLogContainer');
    if (!container) return;

    try {
        let res = await fetch('/api/activity.php');
        if (!res.ok) {
            res = await fetch('/api/activity');
        }

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const data = await res.json();
        const rawActivities = data.activities || (Array.isArray(data) ? data : []);

        if (rawActivities.length > 0) {
            renderLogs(formatActivityLogs(rawActivities), container);
        } else {
            renderEmptyActivityState(container);
        }
    } catch (err) {
        console.warn('Activity API unavailable, attempting fallback:', err);
        renderFallbackLogs(container);
    }
}

function formatActivityLogs(items) {
    return items.map((item, idx) => {
        let icon = 'fa-circle-info';
        let statusClass = 'info';

        const type = (item.activity_type || item.type || '').toLowerCase();
        
        if (type === 'donation_posted' || type === 'success' || type === 'donation') {
            icon = 'fa-box-archive';
            statusClass = 'posted';
        } else if (type === 'request_made' || type === 'warning' || type === 'request') {
            icon = 'fa-hand-holding-heart';
            statusClass = 'completed';
        } else if (type === 'danger' || type === 'rejected') {
            icon = 'fa-circle-xmark';
            statusClass = 'rejected';
        }

        const title = item.title || item.action || 'Activity';
        const description = item.description || item.message || '';
        
        let fullAction = description;
        if (!fullAction) {
            fullAction = title;
        } else if (title && !description.toLowerCase().includes(title.toLowerCase())) {
            fullAction = `${title}: ${description}`;
        }

        const timeStr = item.created_at || item.timestamp || item.date_sent;
        const formattedTime = timeStr ? formatDisplayDate(timeStr) : 'Recently';

        return {
            id: item.id || item.activity_id || `act_${idx}`,
            action: fullAction,
            timestamp: formattedTime,
            status: statusClass,
            icon: icon
        };
    });
}

function renderLogs(logs, container) {
    if (!logs || logs.length === 0) {
        renderEmptyActivityState(container);
        return;
    }

    const safeEscape = (str) => {
        if (window.UIEngine && typeof window.UIEngine.escapeHTML === 'function') {
            return window.UIEngine.escapeHTML(str);
        }
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    container.innerHTML = logs.map(item => `
        <div class="activity-item">
            <div class="activity-icon ${safeEscape(item.status)}">
                <i class="fa-solid ${safeEscape(item.icon)}"></i>
            </div>
            <div class="activity-details">
                <p class="activity-title">${safeEscape(item.action)}</p>
                <span class="activity-time">${safeEscape(item.timestamp)}</span>
            </div>
        </div>
    `).join('');
}

function renderFallbackLogs(container) {
    const fallbackLogs = [
        { 
            action: 'Account Active: Ready to post or claim food surplus', 
            timestamp: 'Just now', 
            status: 'info', 
            icon: 'fa-user-check' 
        }
    ];
    renderLogs(fallbackLogs, container);
}

function renderEmptyActivityState(container) {
    container.innerHTML = `
        <div class="empty-state" style="text-align: center; padding: 24px;">
            <p style="color: #94a3b8;">No recent activity logged yet.</p>
        </div>`;
}

function formatDisplayDate(dateString) {
    if (!dateString) return 'Just now';
    try {
        const isoString = String(dateString).includes('T') ? dateString : String(dateString).replace(' ', 'T');
        const date = new Date(isoString);
        if (isNaN(date.getTime())) return dateString;
        return date.toLocaleString();
    } catch (e) {
        return dateString;
    }
}