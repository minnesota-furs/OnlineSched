export function isElementVisible(element) {
    if (!element) return false;

    let current = element;
    while (current && current.nodeType === Node.ELEMENT_NODE) {
        if (current.hidden || current.style.display === 'none') {
            return false;
        }
        current = current.parentElement;
    }

    return true;
}
