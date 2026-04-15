const HIGHLIGHT_CLASS = 'zc-search-highlight';
const HIGHLIGHT_MS = 3200;

function parseIndex() {
    const el = document.getElementById('zc-search-index');
    if (!el?.textContent) {
        return [];
    }
    try {
        const data = JSON.parse(el.textContent);
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

/**
 * @param {string} q
 * @param {Array<{ id: string, terms: string[] }>} index
 * @returns {string | null}
 */
function findSectionId(q, index) {
    const query = q.trim().toLowerCase();
    if (query.length < 2) {
        return null;
    }

    const validIds = new Set(index.map((row) => row.id));
    if (validIds.has(query) && document.getElementById(query)) {
        return query;
    }

    for (const row of index) {
        for (const term of row.terms) {
            if (!term || term.length < 2) {
                continue;
            }
            if (term.includes(query) || query.includes(term)) {
                return row.id;
            }
        }
    }

    return null;
}

function clearHighlight() {
    document.querySelectorAll(`.${HIGHLIGHT_CLASS}`).forEach((el) => {
        el.classList.remove(HIGHLIGHT_CLASS);
    });
}

let highlightTimer = null;

/**
 * @param {HTMLElement | null} el
 */
function flashHighlight(el) {
    clearHighlight();
    if (highlightTimer) {
        clearTimeout(highlightTimer);
        highlightTimer = null;
    }
    if (!el) {
        return;
    }
    el.classList.add(HIGHLIGHT_CLASS);
    highlightTimer = window.setTimeout(() => {
        el.classList.remove(HIGHLIGHT_CLASS);
        highlightTimer = null;
    }, HIGHLIGHT_MS);
}

function init() {
    const input = document.getElementById('zc-dashboard-search');
    const feedback = document.getElementById('zc-search-feedback');
    const index = parseIndex();

    if (!input || !feedback || index.length === 0) {
        return;
    }

    const minLen = Math.max(1, parseInt(input.getAttribute('data-min-length') || '2', 10) || 2);

    const showNoMatch = () => {
        feedback.classList.remove('hidden');
        feedback.setAttribute('aria-hidden', 'false');
    };

    const hideFeedback = () => {
        feedback.classList.add('hidden');
        feedback.setAttribute('aria-hidden', 'true');
    };

    const runSearch = () => {
        const raw = input.value;
        const query = raw.trim();

        if (query.length < minLen) {
            hideFeedback();
            return;
        }

        const id = findSectionId(query, index);
        if (id) {
            hideFeedback();
            const el = document.getElementById(id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                flashHighlight(el);
            }
            return;
        }

        showNoMatch();
    };

    const searchWrap = input.closest('.relative');

    input.addEventListener('input', () => {
        if (input.value.trim().length < minLen) {
            hideFeedback();
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            runSearch();
        }
        if (e.key === 'Escape') {
            hideFeedback();
            input.blur();
        }
    });

    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof Node)) {
            return;
        }
        if (feedback.contains(t) || input === t || searchWrap?.contains(t)) {
            return;
        }
        hideFeedback();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
