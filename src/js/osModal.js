
export function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    document.body.style.overflow = 'hidden';
    el.showModal();
    // Prevent Safari from showing focus ring on the first focusable element
    if (document.activeElement && document.activeElement !== document.body) {
        document.activeElement.blur();
    }
}

export function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.close();
}

// Call once per dialog on init to wire up backdrop click, close buttons, and scroll restore.
export function initModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.querySelectorAll('.os-close').forEach(btn => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => el.close());
    });
    el.addEventListener('click', (e) => {
        if (e.target === el) el.close();
    });
    el.addEventListener('close', () => {
        if (!document.querySelector('dialog[open]')) {
            document.body.style.overflow = '';
        }
    });
}

