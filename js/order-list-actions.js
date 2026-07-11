document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('show_hidden_orders');
    if (!checkbox) return;

    checkbox.addEventListener('change', function () {
        const url = new URL(window.location.href);
        if (this.checked) {
            url.searchParams.set('show_hidden', '1');
        } else {
            url.searchParams.delete('show_hidden');
        }
        window.location.href = url.toString();
    });
});


