for (const button of document.querySelectorAll('[data-copy-code]')) {
    button.addEventListener('click', async () => {
        const code = button.dataset.copyCode;
        const status = button.closest('.promo-card').querySelector('.promo-copy-status');

        try {
            await navigator.clipboard.writeText(code);
            status.textContent = 'কুপন কোড কপি হয়েছে। কার্টে প্রয়োগ করুন।';
        } catch {
            status.textContent = 'কপি করা যায়নি—কোডটি হাতে কপি করুন: ' + code;
        }
    });
}
