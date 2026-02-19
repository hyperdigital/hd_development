/**
 * HD Development - Copy to Clipboard functionality
 */
class CopyToClipboard {
    constructor() {
        this.init();
    }

    init() {
        document.querySelectorAll('[data-copy-target]').forEach(button => {
            button.addEventListener('click', (e) => this.handleCopy(e));
        });
    }

    async handleCopy(event) {
        const button = event.currentTarget;
        const targetId = button.dataset.copyTarget;
        const targetElement = document.getElementById(targetId);

        if (!targetElement) {
            console.error('Copy target not found:', targetId);
            return;
        }

        const textToCopy = targetElement.innerText;
        const originalText = button.innerHTML;

        try {
            await navigator.clipboard.writeText(textToCopy);
            button.innerHTML = '<span class="icon icon-size-small"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="16" height="16"><path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/></svg></span> Copied!';
            button.classList.add('btn-success');
            button.classList.remove('btn-hd-secondary');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-hd-secondary');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
            button.innerHTML = 'Failed to copy';
            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new CopyToClipboard());
} else {
    new CopyToClipboard();
}
