document.addEventListener('DOMContentLoaded', function () {
    const printButton = document.querySelector('[data-report-print]');
    if (!printButton) return;

    const images = Array.from(document.querySelectorAll('.report-print-header img'));
    const waitForImage = function (image) {
        if (image.complete) {
            return Promise.resolve(image.naturalWidth > 0);
        }

        return new Promise(function (resolve) {
            let timeout;
            const finish = function (loaded) {
                window.clearTimeout(timeout);
                image.removeEventListener('load', onLoad);
                image.removeEventListener('error', onError);
                resolve(loaded);
            };
            const onLoad = function () {
                finish(image.naturalWidth > 0);
            };
            const onError = function () {
                finish(false);
            };

            timeout = window.setTimeout(function () {
                finish(false);
            }, 10000);
            image.addEventListener('load', onLoad, {once: true});
            image.addEventListener('error', onError, {once: true});

            if (image.complete) {
                finish(image.naturalWidth > 0);
            }
        });
    };

    printButton.addEventListener('click', async function () {
        if (printButton.disabled) return;
        printButton.disabled = true;

        try {
            const imagesLoaded = await Promise.all(images.map(waitForImage));
            if (imagesLoaded.some((loaded) => !loaded)) {
                window.alert(printButton.dataset.imageError);
                return;
            }

            window.print();
        } finally {
            printButton.disabled = false;
        }
    });
});