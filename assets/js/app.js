document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.dataset.confirm || 'Continue?')) {
        event.preventDefault();
    }
});
