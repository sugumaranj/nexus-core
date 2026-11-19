/**
 * NexusCore Prelims — 100% Self-Contained Local Browser OCR Engine
 * Uses ONLY project-local assets under /assets/vendor/ocr/ (zero server installs, zero external CDNs).
 * Triggered automatically when a scanned/image-only PDF is uploaded by the FIC.
 */
(function (global) {
    'use strict';

    async function runLocalOcrForPdf(options) {
        var baseUrl = (options.baseUrl || '').replace(/\/$/, '');
        var pdfUrl = options.pdfUrl;
        var onProgress = typeof options.onProgress === 'function' ? options.onProgress : function () {};

        var ocrBase = baseUrl + '/assets/vendor/ocr';

        // 1. Dynamically import local pdf.js ES module
        onProgress('Loading local PDF renderer...', 5);
        var pdfjsLib = await import(ocrBase + '/pdf.min.mjs');
        pdfjsLib.GlobalWorkerOptions.workerSrc = ocrBase + '/pdf.worker.min.mjs';

        // 2. Fetch PDF binary from authenticated FIC stream endpoint
        onProgress('Downloading PDF binary...', 10);
        var resp = await fetch(pdfUrl, { credentials: 'same-origin' });
        if (!resp.ok) {
            throw new Error('Failed to load PDF binary stream (HTTP ' + resp.status + ').');
        }
        var pdfBytes = new Uint8Array(await resp.arrayBuffer());
        var pdfDoc = await pdfjsLib.getDocument({ data: pdfBytes }).promise;
        var totalPages = pdfDoc.numPages;

        // 3. Ensure local tesseract.min.js is loaded
        if (typeof global.Tesseract === 'undefined') {
            onProgress('Loading local OCR engine...', 15);
            await new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = ocrBase + '/tesseract.min.js';
                s.onload = resolve;
                s.onerror = function () { reject(new Error('Failed to load local tesseract.min.js')); };
                document.head.appendChild(s);
            });
        }

        // 4. Create 100% local Tesseract worker pointing to local WASM core & local eng.traineddata.gz
        onProgress('Initializing local English OCR worker...', 20);
        var worker = await global.Tesseract.createWorker('eng', 1, {
            workerPath: ocrBase + '/worker.min.js',
            corePath: ocrBase + '/core',
            langPath: ocrBase + '/lang-data',
            workerBlobURL: false
        });

        var pageTexts = [];
        try {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');

            for (var p = 1; p <= totalPages; p++) {
                var pct = Math.round(20 + ((p - 1) / totalPages) * 75);
                onProgress('OCR scanning page ' + p + ' of ' + totalPages + '...', pct);

                var page = await pdfDoc.getPage(p);
                var viewport = page.getViewport({ scale: 2.0 });
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);

                await page.render({ canvasContext: ctx, viewport: viewport }).promise;
                var result = await worker.recognize(canvas);
                pageTexts.push((result && result.data && result.data.text) ? result.data.text : '');
            }
        } finally {
            await worker.terminate();
        }

        onProgress('OCR complete. Parsing questions...', 98);
        return pageTexts;
    }

    global.NexusLocalOcr = {
        extractPages: runLocalOcrForPdf
    };
})(window);
