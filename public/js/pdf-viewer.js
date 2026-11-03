/* Renders every page of a PDF into a container using pdf.js. */
window.PdfViewer = {
    async render(url, container, onPage) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = document.querySelector('meta[name=pdf-worker]')?.content || '/vendor/pdfjs/pdf.worker.min.js';
        const pdf = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
        const maxWidth = Math.min(container.clientWidth || 900, 900);
        const ratio = window.devicePixelRatio || 1;

        for (let n = 1; n <= pdf.numPages; n++) {
            const page = await pdf.getPage(n);
            const base = page.getViewport({ scale: 1 });
            const scale = maxWidth / base.width;
            const vp = page.getViewport({ scale: scale * ratio });

            const wrap = document.createElement('div');
            wrap.className = 'page';
            wrap.style.width = maxWidth + 'px';
            wrap.dataset.page = n;
            const canvas = document.createElement('canvas');
            canvas.width = vp.width;
            canvas.height = vp.height;
            const overlay = document.createElement('div');
            overlay.className = 'overlay';
            overlay.dataset.page = n;
            wrap.append(canvas, overlay);

            const label = document.createElement('div');
            label.className = 'page-label';
            label.textContent = 'Page ' + n + ' of ' + pdf.numPages;
            container.append(wrap, label);

            await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
            onPage && onPage(n, overlay, wrap);
        }
        return pdf.numPages;
    },
};
window.ESIGN_COLORS = ['#3b5bdb', '#e8590c', '#2b8a3e', '#ae3ec9', '#c92a2a', '#1098ad', '#f08c00', '#5c7cfa'];
window.ESIGN_LABELS = { signature: '✍ Signature', initials: 'Initials', date: 'Date signed', text: 'Text', name: 'Full name' };
