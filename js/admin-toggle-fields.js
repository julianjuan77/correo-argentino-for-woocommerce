document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('[data-toggle-fields]');

    toggles.forEach(toggle => {
        const targetSelectors = toggle.getAttribute('data-toggle-fields');
        if (!targetSelectors) return;

        const targets = document.querySelectorAll(targetSelectors);

        const updateState = () => {
            const enabled = toggle.checked;
            targets.forEach(target => {
                if (target) target.disabled = !enabled;
            });
        };

        updateState();

        toggle.addEventListener('change', updateState);
    });
});
